<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

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
