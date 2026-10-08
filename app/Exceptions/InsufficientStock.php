<?php

namespace App\Exceptions;

use DomainException;

/** Raised when an order cannot be taken out of stock because some units are already gone. */
class InsufficientStock extends DomainException
{
    /** @param  list<string>  $shortages  one line per product that falls short */
    public function __construct(public readonly array $shortages)
    {
        parent::__construct('No hay stock suficiente: '.implode('; ', $shortages));
    }
}
