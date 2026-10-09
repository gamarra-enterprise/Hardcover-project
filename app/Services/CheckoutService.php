<?php

namespace App\Services;

use App\Exceptions\CheckoutBlocked;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\OrderPlaced;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Turns the visitor's cart into an order waiting for payment. It does not touch the stock: that
 * goes down only when the payment is confirmed. The order keeps its own copy of the products,
 * prices and address, so later changes to the shop never alter it.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly ShippingService $shipping,
    ) {}

    /**
     * What the buyer would pay for this cart sent to this district. With no district yet, the
     * shipping is still unknown and counts as zero.
     *
     * @return array{subtotal: string, shipping_cost: string, tax: string, total: string, quote: ?ShippingQuote}
     */
    public function totals(CartSummary $summary, ?string $ubigeo): array
    {
        $quote = $ubigeo ? $this->shipping->quote($ubigeo, $summary->subtotal) : null;

        return [...Order::totalsFor($summary->subtotal, $quote?->cost ?? '0'), 'quote' => $quote];
    }

    /**
     * @param  array{name: string, email: string, phone: string, ubigeo: string, line1: string, line2?: ?string, save_address?: bool}  $data
     *
     * @throws CheckoutBlocked
     */
    public function placeOrder(array $data, ?User $user = null): Order
    {
        $summary = $this->cart->summary();

        if ($summary->isEmpty()) {
            throw new CheckoutBlocked('Tu carrito está vacío.');
        }

        if ($summary->hasIssues()) {
            throw new CheckoutBlocked('Algunos productos de tu carrito cambiaron. Revisa los avisos antes de continuar.');
        }

        $totals = $this->totals($summary, $data['ubigeo']);
        $quote = $totals['quote'];

        if (! $quote) {
            throw new CheckoutBlocked('Todavía no enviamos a ese distrito.');
        }

        $district = $quote->district;
        $address = [
            'recipient_name' => $data['name'],
            'phone' => $data['phone'],
            'line1' => $data['line1'],
            'line2' => $data['line2'] ?? null,
            'city' => $district->name,
            'state' => 'Lima',
            'ubigeo' => $district->ubigeo,
            'postal_code' => null,
            'country' => 'PE',
            'zone' => $district->zone->name,
        ];

        $order = DB::transaction(function () use ($summary, $totals, $address, $data, $user) {
            $order = Order::create([
                'user_id' => $user?->id,
                'email' => $data['email'],
                'shipping_address' => $address,
                ...collect($totals)->only(['subtotal', 'shipping_cost', 'tax', 'total'])->all(),
            ]);

            foreach ($summary->lines as $line) {
                $order->items()->create(OrderItem::valuesFor($line->product, $line->quantity));
            }

            if ($user && ($data['save_address'] ?? false)) {
                $this->saveAddress($user, $address);
            }

            $this->cart->clear();

            return $order;
        });

        // A mail problem must never undo an order that is already saved.
        try {
            if ($email = $order->contactEmail()) {
                Notification::route('mail', $email)->notify(new OrderPlaced($order->load('items')));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $order;
    }

    /** Keep the delivery address in the customer's account, once. The first one becomes the default. */
    private function saveAddress(User $user, array $address): void
    {
        $columns = collect($address)->except('zone')->all();

        Address::firstOrCreate(
            ['user_id' => $user->id, 'ubigeo' => $address['ubigeo'], 'line1' => $address['line1']],
            [...$columns, 'type' => 'shipping', 'is_default' => ! $user->addresses()->exists()],
        );
    }
}
