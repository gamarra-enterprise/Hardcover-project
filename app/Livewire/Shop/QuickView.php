<?php

namespace App\Livewire\Shop;

use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/** The window that opens over any listing to look at a product and add it without leaving the page. */
class QuickView extends Component
{
    #[Locked]
    public ?int $productId = null;

    #[On('quick-view')]
    public function open(int $id): void
    {
        // Hidden products cannot be opened this way either.
        $this->productId = Product::visible()->whereKey($id)->value('id');
    }

    public function close(): void
    {
        $this->productId = null;
    }

    #[Computed]
    public function product(): ?Product
    {
        return $this->productId ? Product::visible()->with(['bookDetail', 'categories'])->find($this->productId) : null;
    }

    public function render()
    {
        return view('livewire.shop.quick-view');
    }
}
