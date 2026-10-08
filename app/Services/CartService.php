<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The cart of the current visitor: stored in the database for logged-in users and, for guests,
 * tied to a random token kept in the session (it survives the session id change at login).
 * Nothing here touches product stock; see InventoryService for the rules.
 */
class CartService
{
    private const GUEST_TOKEN_KEY = 'cart_token';

    public function __construct(private readonly InventoryService $inventory) {}

    /** The visitor's cart, or null for a guest who never added anything. */
    public function current(bool $create = false): ?Cart
    {
        if ($user = auth()->user()) {
            return $create ? Cart::firstOrCreate(['user_id' => $user->id]) : Cart::where('user_id', $user->id)->first();
        }

        $token = session(self::GUEST_TOKEN_KEY);

        if ($token && ($cart = Cart::where('session_id', $token)->whereNull('user_id')->first())) {
            return $cart;
        }

        if (! $create) {
            return null;
        }

        $token = Str::random(40);
        session([self::GUEST_TOKEN_KEY => $token]);

        return Cart::create(['session_id' => $token]);
    }

    /**
     * Add units of a product. The quantity is limited to the stock because a buyer cannot take
     * more than exist, but other carts are never taken into account.
     *
     * @return array{added: int, in_cart: int, limited: bool}
     */
    public function add(Product $product, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);
        $available = $this->inventory->availableFor($product);
        $cart = $this->current(create: $available > 0);

        if (! $cart) {
            return ['added' => 0, 'in_cart' => 0, 'limited' => true];
        }

        $item = $this->item($cart, $product);
        $before = $item->exists ? $item->quantity : 0;

        $target = min($before + $quantity, $available);
        $added = max(0, $target - $before);

        if ($added > 0) {
            $item->quantity = $target;
            $this->saveItem($item);
            $cart->touch();
        }

        return ['added' => $added, 'in_cart' => max($before, $target), 'limited' => $added < $quantity];
    }

    /** Set the quantity of a line of the current cart. Zero or less removes it. */
    public function setQuantity(int $itemId, int $quantity): void
    {
        $item = $this->findItem($itemId);

        if (! $item) {
            return;
        }

        if ($quantity <= 0) {
            $item->delete();
        } else {
            // Never above the stock, except that a sold-out line keeps its number so it can be seen.
            $available = $this->inventory->availableFor($item->product);
            $item->update(['quantity' => $available > 0 ? min($quantity, $available) : $item->quantity]);
        }

        $item->cart->touch();
    }

    public function remove(int $itemId): void
    {
        $this->setQuantity($itemId, 0);
    }

    public function clear(): void
    {
        $this->current()?->items()->delete();
    }

    public function count(): int
    {
        return (int) ($this->current()?->items()->sum('quantity') ?? 0);
    }

    /** Units of this product already in the visitor's cart. */
    public function quantityOf(Product $product): int
    {
        return (int) ($this->current()?->items()->where('product_id', $product->id)->value('quantity') ?? 0);
    }

    public function summary(): CartSummary
    {
        $items = $this->current()?->items()->with(['product.bookDetail'])->orderBy('id')->get() ?? collect();

        $lines = [];
        $subtotal = '0.00';
        $count = 0;

        foreach ($items as $item) {
            $product = $item->product;
            $issue = $this->inventory->issueFor($product, $item->quantity);
            $total = bcmul($product->currentPrice(), (string) $item->quantity, 2);

            $lines[] = new CartLine($item, $product, $item->quantity, $product->currentPrice(), $total, $this->inventory->availableFor($product), $issue);
            $count += $item->quantity;

            if ($issue === null) {
                $subtotal = bcadd($subtotal, $total, 2);
            }
        }

        return new CartSummary($lines, $count, $subtotal, (float) config('shop.free_shipping_from'));
    }

    /**
     * On login the guest cart joins the user's cart: quantities add up, never above the stock.
     * The guest cart is then deleted.
     */
    public function mergeGuestCartInto(User $user): void
    {
        $token = session(self::GUEST_TOKEN_KEY);
        $guest = $token ? Cart::where('session_id', $token)->whereNull('user_id')->first() : null;

        if (! $guest) {
            return;
        }

        DB::transaction(function () use ($guest, $user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            foreach ($guest->items()->with('product')->get() as $line) {
                $item = $this->item($cart, $line->product);
                $total = ($item->exists ? $item->quantity : 0) + $line->quantity;
                $available = $this->inventory->availableFor($line->product);

                $item->quantity = $available > 0 ? min($total, $available) : $total;
                $this->saveItem($item);
            }

            $guest->delete();
            $cart->touch();
        });

        session()->forget(self::GUEST_TOKEN_KEY);
    }

    private function item(Cart $cart, Product $product): CartItem
    {
        return $cart->items()->firstOrNew(['product_id' => $product->id]);
    }

    /** Two requests adding the same product at once can race on the unique index; the second one adds up. */
    private function saveItem(CartItem $item): void
    {
        try {
            $item->save();
        } catch (UniqueConstraintViolationException) {
            $existing = CartItem::where('cart_id', $item->cart_id)->where('product_id', $item->product_id)->first();
            $existing?->update(['quantity' => max($existing->quantity, $item->quantity)]);
        }
    }

    /** A line of the current cart only: an id from another cart is never found. */
    private function findItem(int $itemId): ?CartItem
    {
        return $this->current()?->items()->with('product')->whereKey($itemId)->first();
    }
}
