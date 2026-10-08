<?php

namespace App\Exceptions;

use DomainException;

/** The order cannot be paid right now; the message tells the customer why and is safe to show. */
class PaymentNotAllowed extends DomainException {}
