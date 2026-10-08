<?php

namespace App\Listeners;

use App\Services\CartService;
use Illuminate\Auth\Events\Login;

class MergeGuestCart
{
    public function __construct(private readonly CartService $cart) {}

    public function handle(Login $event): void
    {
        $this->cart->mergeGuestCartInto($event->user);
    }
}
