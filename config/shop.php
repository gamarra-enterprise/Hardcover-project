<?php

return [
    // Orders from this amount (IGV included) ship for free.
    'free_shipping_from' => (float) env('SHOP_FREE_SHIPPING_FROM', 150),

    // Which gateway takes the payments: "mercadopago", or "fake" for local development without
    // credentials (a page that approves or rejects the payment; refused in production).
    'payment_gateway' => env('PAYMENT_GATEWAY', 'mercadopago'),
];
