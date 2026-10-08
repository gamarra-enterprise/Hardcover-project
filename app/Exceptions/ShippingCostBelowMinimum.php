<?php

namespace App\Exceptions;

use App\Support\Money;
use DomainException;

class ShippingCostBelowMinimum extends DomainException
{
    public function __construct(public readonly string $zone, public readonly string $minimum)
    {
        parent::__construct('El costo de envío de '.$zone.' no puede ser menor a '.Money::format($minimum).'.');
    }
}
