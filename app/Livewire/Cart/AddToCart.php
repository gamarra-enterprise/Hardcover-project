<?php

namespace App\Livewire\Cart;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\CartService;
use App\Services\InventoryService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** The quantity selector and the add button of the product page. */
class AddToCart extends Component
{
    #[Locked]
    public int $productId;

    public int $quantity = 1;

    public ?string $message = null;

    public bool $isWarning = false;

    public function mount(Product $product): void
    {
        $this->productId = $product->id;
    }

    #[Computed]
    public function product(): Product
    {
        return Product::findOrFail($this->productId);
    }

    #[Computed]
    public function available(): int
    {
        return app(InventoryService::class)->availableFor($this->product);
    }

    #[Computed]
    public function inCart(): int
    {
        return app(CartService::class)->quantityOf($this->product);
    }

    /** Units still to add: what is in stock minus what this buyer already has. */
    #[Computed]
    public function room(): int
    {
        return max(0, $this->available - $this->inCart);
    }

    public function increment(): void
    {
        $this->quantity = min($this->quantity + 1, max(1, $this->room));
    }

    public function decrement(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function add(): void
    {
        $result = app(CartService::class)->add($this->product, max(1, $this->quantity));

        unset($this->inCart, $this->room);
        $this->quantity = 1;

        if ($result['added'] === 0) {
            $this->message = 'Ya tienes en tu carrito todas las unidades disponibles.';
            $this->isWarning = true;
        } elseif ($result['limited']) {
            $this->message = "Solo había unidades para agregar {$result['added']}. Revisa tu carrito.";
            $this->isWarning = true;
        } else {
            $this->message = 'Añadido a tu carrito.';
            $this->isWarning = false;
        }

        $this->dispatch('cart-updated');
        $this->dispatch('cart-open');
    }

    public function unavailableLabel(): string
    {
        return $this->product->status === ProductStatus::OUT_OF_STOCK ? 'Sin stock' : 'No disponible';
    }

    public function render()
    {
        return view('livewire.cart.add-to-cart');
    }
}
