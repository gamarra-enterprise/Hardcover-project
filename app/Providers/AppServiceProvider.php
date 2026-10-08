<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Payments\FakeGateway;
use App\Payments\MercadoPagoGateway;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, fn () => match (config('shop.payment_gateway')) {
            'mercadopago' => new MercadoPagoGateway(
                config('services.mercadopago.access_token'),
                config('services.mercadopago.webhook_secret'),
                config('services.mercadopago.base_url'),
            ),
            // The stand-in must never take real orders.
            'fake' => $this->app->isProduction()
                ? throw new \RuntimeException('PAYMENT_GATEWAY=fake is not allowed in production.')
                : new FakeGateway,
            default => throw new \RuntimeException('Unknown payment gateway: '.config('shop.payment_gateway')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The super admin has full control over every resource, so no policy can deny them.
        Gate::before(fn (User $user) => $user->role === UserRole::SUPER_ADMIN ? true : null);
    }
}
