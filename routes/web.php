<?php

use App\Http\Controllers\AccountOrderController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ClubController as AdminClubController;
use App\Http\Controllers\Admin\HomeController as AdminHomeController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\TransferReviewController;
use App\Http\Controllers\Admin\ShippingController as AdminShippingController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Super\ActivityController as SuperActivityController;
use App\Http\Controllers\Super\BackupController as SuperBackupController;
use App\Http\Controllers\Super\GatewayController as SuperGatewayController;
use App\Http\Controllers\Super\UserController as SuperUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\Payments\CardPaymentController;
use App\Http\Controllers\Payments\FakeGatewayController;
use App\Http\Controllers\Payments\PayController;
use App\Http\Controllers\Payments\ReturnController;
use App\Http\Controllers\Payments\TransferController;
use App\Http\Controllers\Payments\WebhookController;
use App\Http\Controllers\ProductController;
use App\Livewire\Account\Addresses;
use App\Livewire\Actions\Logout;
use App\Livewire\Cart\CartPage;
use App\Livewire\Checkout\Checkout;
use App\Livewire\Orders\TrackOrder;
use App\Livewire\Shop\ProductList;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('nosotros', [PageController::class, 'about'])->name('about');
Route::get('preguntas-frecuentes', [PageController::class, 'faq'])->name('faq');
Route::get('catalogo', ProductList::class)->name('catalog');
Route::get('novedades', ProductList::class)->defaults('collection', 'novedades')->name('collection.novedades');
Route::get('mas-vendidos', ProductList::class)->defaults('collection', 'masvendidos')->name('collection.masvendidos');
Route::get('ofertas', ProductList::class)->defaults('collection', 'ofertas')->name('collection.ofertas');
Route::get('regalos', ProductList::class)->defaults('collection', 'regalos')->name('collection.regalos');
Route::post('club', [ClubController::class, 'subscribe'])->middleware('throttle:5,1')->name('club.subscribe');
Route::get('club/baja/{subscriber}', [ClubController::class, 'unsubscribe'])->name('club.unsubscribe');
Route::get('carrito', CartPage::class)->name('cart');
Route::get('checkout', Checkout::class)->name('checkout');
Route::get('seguimiento', TrackOrder::class)->name('orders.track');
Route::get('pedido/{order:tracking_code}', [OrderController::class, 'show'])->name('orders.show');
Route::get('pedido/{order:tracking_code}/tarjeta', [CardPaymentController::class, 'show'])->name('orders.card');
Route::post('pedido/{order:tracking_code}/tarjeta', [CardPaymentController::class, 'store'])->name('orders.card.pay');
Route::post('pedido/{order:tracking_code}/transferencia', TransferController::class)->middleware('throttle:10,1')->name('orders.transfer');
Route::post('pedido/{order:tracking_code}/pagar', PayController::class)->name('orders.pay');
Route::get('pago/retorno/{order:tracking_code}', ReturnController::class)->name('payments.return');
Route::post('webhooks/mercadopago', WebhookController::class)->middleware('throttle:120,1')->name('payments.webhook');

// The stand-in gateway for local development; the controller refuses everything else.
Route::get('pago/prueba/{paymentId}', [FakeGatewayController::class, 'show'])->name('payments.fake.show');
Route::post('pago/prueba/{paymentId}', [FakeGatewayController::class, 'decide'])->name('payments.fake.decide');
Route::get('producto/{product:slug}', [ProductController::class, 'show'])->name('products.show');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('seguridad', [TwoFactorController::class, 'show'])->name('security.show');
    Route::post('seguridad/activar', [TwoFactorController::class, 'start'])->name('security.start');
    Route::post('seguridad/confirmar', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('security.confirm');
    Route::delete('seguridad', [TwoFactorController::class, 'disable'])->middleware('throttle:10,1')->name('security.disable');
    Route::get('dos-pasos', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('dos-pasos', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::post('salir', function (Logout $logout) {
        $logout();

        return redirect()->route('home');
    })->name('logout');
    Route::get('cuenta/pedidos', [AccountOrderController::class, 'index'])->name('account.orders');
    Route::get('cuenta/direcciones', Addresses::class)->name('account.addresses');
    Route::post('pedido/{order:tracking_code}/cancelar', [AccountOrderController::class, 'cancel'])->name('orders.cancel');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Site management: products, categories, stock and orders. Admin and super admin.
Route::middleware(['auth', 'verified', 'role:admin', 'two-factor'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminHomeController::class)->name('index');
    Route::get('pagos/{payment}/comprobante', [TransferReviewController::class, 'proof'])->name('payments.proof');
    Route::post('pagos/{payment}/confirmar', [TransferReviewController::class, 'confirm'])->name('payments.confirm');
    Route::post('pagos/{payment}/rechazar', [TransferReviewController::class, 'reject'])->name('payments.reject');
    Route::post('pagos/{payment}/reembolsado', [TransferReviewController::class, 'refunded'])->name('payments.refunded');
    Route::get('inventario', [InventoryController::class, 'index'])->name('inventory.index');
    Route::patch('inventario/{product}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::get('club', [AdminClubController::class, 'index'])->name('club.index');
    Route::get('club/exportar', [AdminClubController::class, 'export'])->name('club.export');
    Route::get('ventas', SalesController::class)->name('sales');
    Route::get('pedidos', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('pedidos/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('pedidos/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::get('productos/exportar', [AdminProductController::class, 'export'])->name('products.export');
    Route::resource('productos', AdminProductController::class)->parameters(['productos' => 'product'])->names('products')->except(['show', 'destroy']);
    Route::get('envios', [AdminShippingController::class, 'index'])->name('shipping.index');
    Route::put('envios/ajustes', [AdminShippingController::class, 'updateSettings'])->name('shipping.settings');
    Route::put('envios/zonas/{zone}', [AdminShippingController::class, 'updateZone'])->name('shipping.zones.update');
    Route::post('envios/zonas/{zone}/distritos', [AdminShippingController::class, 'storeDistrict'])->name('shipping.districts.store');
    Route::put('envios/distritos/{district}', [AdminShippingController::class, 'updateDistrict'])->name('shipping.districts.update');
    Route::patch('categorias/{category}/destacada', [AdminCategoryController::class, 'toggleFeatured'])->name('categories.featured');
    Route::resource('categorias', AdminCategoryController::class)->parameters(['categorias' => 'category'])->names('categories')->except(['show']);
});

// Users and roles, payment gateways, shipping rates and activity. Super admin only.
Route::middleware(['auth', 'verified', 'role:super_admin', 'two-factor'])->prefix('super')->name('super.')->group(function () {
    Route::view('/', 'super.index')->name('index');
    Route::get('usuarios', [SuperUserController::class, 'index'])->name('users.index');
    Route::patch('usuarios/{user}/rol', [SuperUserController::class, 'updateRole'])->name('users.role');
    Route::get('pasarelas', SuperGatewayController::class)->name('gateways');
    Route::get('respaldos', [SuperBackupController::class, 'index'])->name('backups.index');
    Route::post('respaldos', [SuperBackupController::class, 'store'])->middleware('throttle:6,1')->name('backups.store');
    Route::get('respaldos/{name}', [SuperBackupController::class, 'download'])->name('backups.download');
    Route::delete('respaldos/{name}', [SuperBackupController::class, 'destroy'])->name('backups.destroy');
    Route::delete('usuarios/{user}/dos-pasos', [SuperUserController::class, 'resetTwoFactor'])->name('users.2fa-reset');
    Route::get('actividad', SuperActivityController::class)->name('activity');
});

require __DIR__.'/auth.php';
