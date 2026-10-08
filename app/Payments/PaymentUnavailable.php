<?php

namespace App\Payments;

use RuntimeException;

/** The gateway is not set up or did not answer as expected. The message is safe to log, never to show. */
class PaymentUnavailable extends RuntimeException {}
