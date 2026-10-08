<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Notifications\OrderStatusChanged;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

#[Fillable(['user_id', 'tracking_code', 'email', 'status', 'subtotal', 'shipping_cost', 'tax', 'total', 'stock_deducted_at', 'refund_amount', 'shipping_address'])]
class Order extends Model
{
    use HasFactory;

    /** IGV rate included in every price. */
    public const IGV_PERCENT = 18;

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->tracking_code ??= static::newTrackingCode();
            // The column has a default, but the history line written on creation needs the value now.
            $order->status ??= OrderStatus::PENDING;
        });

        // The first line of the history is the creation of the order.
        static::created(function (Order $order) {
            $order->statusHistories()->create(['from_status' => null, 'to_status' => $order->status, 'note' => 'Pedido creado']);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'stock_deducted_at' => 'datetime',
            'shipping_address' => 'array',
        ];
    }

    /** Format HB-yymmdd-NNNN, retried until it is unique. */
    public static function newTrackingCode(): string
    {
        do {
            $code = sprintf('HB-%s-%04d', now()->format('ymd'), random_int(0, 9999));
        } while (static::where('tracking_code', $code)->exists());

        return $code;
    }

    /**
     * Prices already include IGV, so the subtotal is the sum of the lines, the total adds
     * shipping, and the tax is only the IGV portion contained in that total.
     *
     * @return array{subtotal: string, shipping_cost: string, tax: string, total: string}
     */
    public static function totalsFor(string $subtotal, string $shippingCost): array
    {
        $total = bcadd($subtotal, $shippingCost, 2);
        $tax = Money::fraction($total, self::IGV_PERCENT, 100 + self::IGV_PERCENT);

        return [
            'subtotal' => bcadd($subtotal, '0', 2),
            'shipping_cost' => bcadd($shippingCost, '0', 2),
            'tax' => $tax,
            'total' => $total,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Link to follow this order without an account. It is signed, so only whoever was given the
     * link (at checkout, or after proving code and e-mail) can open it; the code alone is not enough.
     */
    public function signedUrl(int $days = 14): string
    {
        return URL::temporarySignedRoute('orders.show', now()->addDays($days), ['order' => $this->tracking_code]);
    }

    /** Address the "pay" button posts to; signed, so it works for a guest holding the order link. */
    public function payUrl(): string
    {
        return URL::temporarySignedRoute('orders.pay', now()->addHours(2), ['order' => $this->tracking_code]);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    /** E-mail for status notices: the one given at checkout, or the account's. */
    public function contactEmail(): ?string
    {
        return $this->email ?: $this->user?->email;
    }

    /**
     * Move the order to another status, if the transition is allowed. The change and its history
     * line are saved together, and the customer is told by e-mail.
     *
     * @throws InvalidOrderTransition
     */
    public function transitionTo(OrderStatus $to, ?User $by = null, ?string $note = null, bool $notify = true): void
    {
        DB::transaction(function () use ($to, $by, $note) {
            // Lock the row so two people changing the same order at once cannot both succeed.
            $current = static::lockForUpdate()->findOrFail($this->id)->status;

            if (! $current->canTransitionTo($to)) {
                throw new InvalidOrderTransition($current, $to);
            }

            $this->forceFill(['status' => $to])->save();
            $this->statusHistories()->create(['from_status' => $current, 'to_status' => $to, 'changed_by' => $by?->id, 'note' => $note]);
        });

        if ($notify) {
            $this->notifyStatus($to);
        }
    }

    /** Tell the customer the order is now in this status, when there is an e-mail to tell. */
    public function notifyStatus(OrderStatus $status, ?string $reason = null): void
    {
        if ($email = $this->contactEmail()) {
            Notification::route('mail', $email)->notify(new OrderStatusChanged($this, $status, $reason));
        }
    }
}
