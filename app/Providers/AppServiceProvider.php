<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Payments\FakeGateway;
use App\Payments\MercadoPagoGateway;
use App\Payments\PaymentGateway;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
                config('services.mercadopago.public_key'),
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
        // The menu of the shop lists the genres that have products.
        View::composer('components.shop-layout', fn ($view) => $view->with('menuGenres', \App\Support\MenuGenres::all()));

        // The super admin has full control over every resource, so no policy can deny them.
        Gate::before(fn (User $user) => $user->role === UserRole::SUPER_ADMIN ? true : null);
    }
}
