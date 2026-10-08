<?php

namespace App\Livewire\Cart;

use Livewire\Component;

class CartDrawer extends Component
{
    use ManagesCartLines;

    public function render()
    {
        return view('livewire.cart.cart-drawer');
    }
}
