<?php

namespace App\Livewire\Cart;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.shop-layout')]
#[Title('Carrito')]
class CartPage extends Component
{
    use ManagesCartLines;

    public function render()
    {
        return view('livewire.cart.cart-page');
    }
}
