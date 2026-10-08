<?php

namespace App\Payments;

/**
 * What the shop receives from the card form: a one-time token made by the gateway in the customer's
 * browser, plus who pays. The card number and the security code never reach the shop's server,
 * only this token, which is useless anywhere else.
 */
final readonly class CardCharge
{
    public function __construct(
        public string $token,
        public string $paymentMethodId,
        public ?string $issuerId,
        public int $installments,
        public string $email,
        public string $identificationType,
        public string $identificationNumber,
        public ?string $cardholderName = null,
    ) {}
}
