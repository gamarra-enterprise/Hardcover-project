<?php

namespace App\Exceptions;

use DomainException;

/** The order cannot be placed as the cart or the destination stand now; the message tells the buyer why. */
class CheckoutBlocked extends DomainException {}
