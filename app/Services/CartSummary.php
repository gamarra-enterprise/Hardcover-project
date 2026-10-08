<?php

namespace App\Services;

final readonly class CartSummary
{
    /**
     * @param  list<CartLine>  $lines
     * @param  string  $subtotal  sum of the lines that can be bought (IGV included)
     */
    public function __construct(
        public array $lines,
        public int $count,
        public string $subtotal,
        public float $freeShippingFrom,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function hasIssues(): bool
    {
        return (bool) array_filter($this->lines, fn (CartLine $line) => $line->issue !== null);
    }

    /** Amount missing for free shipping, zero once reached. */
    public function missingForFreeShipping(): string
    {
        return bcsub((string) max(0, $this->freeShippingFrom - (float) $this->subtotal), '0', 2);
    }

    /** Progress toward free shipping from 0 to 100. */
    public function freeShippingProgress(): int
    {
        return $this->freeShippingFrom > 0 ? (int) min(100, floor((float) $this->subtotal / $this->freeShippingFrom * 100)) : 100;
    }
}
