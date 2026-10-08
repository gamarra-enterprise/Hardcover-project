<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use DomainException;

class OrderNotCancellable extends DomainException
{
    public function __construct(public readonly OrderStatus $status)
    {
        parent::__construct("Un pedido «{$status->label()}» ya no se puede cancelar.");
    }
}
