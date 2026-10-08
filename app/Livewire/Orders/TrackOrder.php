<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lets a customer without an account open their order by proving two things: the order code and the
 * e-mail it was placed with. The answer is the same whichever of the two is wrong.
 */
#[Layout('components.shop-layout')]
#[Title('Seguimiento de pedido')]
class TrackOrder extends Component
{
    public string $code = '';

    public string $email = '';

    public function search(): mixed
    {
        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $key = 'track-order:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            $this->addError('code', 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $email = mb_strtolower(trim($this->email));
        $order = Order::where('tracking_code', strtoupper(trim($this->code)))
            ->where(fn ($q) => $q
                ->whereRaw('lower(email) = ?', [$email])
                ->orWhereHas('user', fn ($u) => $u->whereRaw('lower(email) = ?', [$email])))
            ->first();

        if (! $order) {
            $this->addError('code', 'No encontramos un pedido con esos datos. Revisa el código y el correo.');

            return null;
        }

        return $this->redirect($order->signedUrl(), navigate: false);
    }

    public function render()
    {
        return view('livewire.orders.track-order');
    }
}
