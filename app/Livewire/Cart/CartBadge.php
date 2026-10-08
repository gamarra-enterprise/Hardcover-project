<?php

namespace App\Livewire\Cart;

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartBadge extends Component
{
    #[On('cart-updated')]
    public function refreshBadge(): void
    {
        // Nothing to do: the component renders again and reads the new count.
    }

    public function render()
    {
        return view('livewire.cart.cart-badge', ['count' => app(CartService::class)->count()]);
    }
}
