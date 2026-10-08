<?php

namespace App\Livewire\Cart;

use App\Services\CartService;
use App\Services\CartSummary;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * Shared by the cart drawer and the cart page. Every action works on the visitor's own cart
 * only, whatever line id the browser sends.
 */
trait ManagesCartLines
{
    #[Computed]
    public function summary(): CartSummary
    {
        return app(CartService::class)->summary();
    }

    public function changeBy(int $itemId, int $delta): void
    {
        $line = collect($this->summary->lines)->first(fn ($l) => $l->item->id === $itemId);

        if ($line) {
            app(CartService::class)->setQuantity($itemId, $line->quantity + $delta);
            $this->cartChanged();
        }
    }

    /** Used to bring a line down to the units that are left. */
    public function setTo(int $itemId, int $quantity): void
    {
        app(CartService::class)->setQuantity($itemId, $quantity);
        $this->cartChanged();
    }

    public function remove(int $itemId): void
    {
        app(CartService::class)->remove($itemId);
        $this->cartChanged();
    }

    /** The summary is cached for the request, so it is dropped after every change. */
    private function cartChanged(): void
    {
        unset($this->summary);
        $this->dispatch('cart-updated');
    }

    /** Another component changed the cart: render again. */
    #[On('cart-updated')]
    public function refreshCart(): void
    {
        unset($this->summary);
    }
}
