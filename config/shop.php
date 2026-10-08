<?php

return [
    // Orders from this amount (IGV included) ship for free.
    'free_shipping_from' => (float) env('SHOP_FREE_SHIPPING_FROM', 150),
];
