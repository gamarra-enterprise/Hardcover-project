<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Livewire\Cart\CartPage;
use App\Livewire\Checkout\Checkout;
use App\Livewire\Orders\TrackOrder;
use App\Livewire\Shop\ProductList;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('nosotros', [PageController::class, 'about'])->name('about');
Route::get('preguntas-frecuentes', [PageController::class, 'faq'])->name('faq');
Route::get('catalogo', ProductList::class)->name('catalog');
Route::get('carrito', CartPage::class)->name('cart');
Route::get('checkout', Checkout::class)->name('checkout');
Route::get('seguimiento', TrackOrder::class)->name('orders.track');
Route::get('pedido/{order:tracking_code}', [OrderController::class, 'show'])->name('orders.show');
Route::get('producto/{product:slug}', [ProductController::class, 'show'])->name('products.show');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Site management: products, categories, stock and orders. Admin and super admin.
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.index')->name('index');
});

// Users and roles, payment gateways, shipping rates and activity. Super admin only.
Route::middleware(['auth', 'verified', 'role:super_admin'])->prefix('super')->name('super.')->group(function () {
    Route::view('/', 'super.index')->name('index');
});

require __DIR__.'/auth.php';
