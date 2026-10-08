<?php

namespace App\Policies;

use App\Models\ShippingDistrict;
use App\Models\User;

/**
 * Shipping zones, districts and rates are managed by the administrator (the super admin
 * passes through Gate::before). Customers never see these screens: the shop reads the rates
 * through ShippingService.
 */
class ShippingDistrictPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, ShippingDistrict $model): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, ShippingDistrict $model): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, ShippingDistrict $model): bool
    {
        return $user->isStaff();
    }
}
