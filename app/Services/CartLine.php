<?php

namespace App\Services;

use App\Enums\CartLineIssue;
use App\Models\CartItem;
use App\Models\Product;

final readonly class CartLine
{
    public function __construct(
        public CartItem $item,
        public Product $product,
        public int $quantity,
        public string $unitPrice,
        public string $total,
        public int $available,
        public ?CartLineIssue $issue,
    ) {}

    public function issueMessage(): ?string
    {
        return $this->issue?->message($this->available);
    }
}
