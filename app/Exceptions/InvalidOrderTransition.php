<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use DomainException;

class InvalidOrderTransition extends DomainException
{
    public function __construct(public readonly OrderStatus $from, public readonly OrderStatus $to)
    {
        parent::__construct("Un pedido «{$from->label()}» no puede pasar a «{$to->label()}».");
    }
}
