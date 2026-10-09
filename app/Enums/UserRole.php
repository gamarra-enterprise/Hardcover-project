<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super administrador',
            self::ADMIN => 'Administrador',
            self::CUSTOMER => 'Cliente',
        };
    }

    public function isStaff(): bool
    {
        return $this !== self::CUSTOMER;
    }
}
