<?php

return [
    // Orders from this amount (IGV included) ship for free.
    'free_shipping_from' => (float) env('SHOP_FREE_SHIPPING_FROM', 150),

    // Which gateway takes the payments: "mercadopago", or "fake" for local development without
    // credentials (a page that approves or rejects the payment; refused in production).
    'payment_gateway' => env('PAYMENT_GATEWAY', 'mercadopago'),

    // Bank transfer: the customer pays into this account and uploads the proof; staff confirm it in the panel.
    // It is offered only when the account number is set.
    'bank_transfer' => [
        'bank' => env('SHOP_BANK_NAME'),
        'holder' => env('SHOP_BANK_HOLDER'),
        'account' => env('SHOP_BANK_ACCOUNT'),
        'cci' => env('SHOP_BANK_CCI'),
    ],

    // Staff must use two-step verification to enter the panels. On by default in production;
    // off in development so the seeded accounts keep working.
    'require_two_factor' => (bool) env('SHOP_REQUIRE_2FA', env('APP_ENV') === 'production'),
];
