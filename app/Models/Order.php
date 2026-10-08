<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'tracking_code', 'status', 'subtotal', 'shipping_cost', 'tax', 'total', 'shipping_address'])]
class Order extends Model
{
    use HasFactory;

    /** IGV rate included in every price. */
    public const IGV_PERCENT = 18;

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->tracking_code ??= static::newTrackingCode();
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
        $tax = bcdiv(bcmul($total, (string) self::IGV_PERCENT, 4), (string) (100 + self::IGV_PERCENT), 2);

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
}
