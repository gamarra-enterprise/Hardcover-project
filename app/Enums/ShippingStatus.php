<?php

namespace App\Enums;

enum ShippingStatus: string
{
    case PENDING = 'pending';
    case PREPARING = 'preparing';
    case SHIPPED = 'shipped';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case FAILED_DELIVERY = 'failed_delivery';
    case RETURNED = 'returned';
}