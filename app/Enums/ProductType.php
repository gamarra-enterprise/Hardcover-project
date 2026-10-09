<?php

namespace App\Enums;

/** What kind of thing a product is. Books carry book details; the rest are the shop's stationery, accessories and figures. */
enum ProductType: string
{
    case BOOK = 'libro';
    case STATIONERY = 'papeleria';
    case ACCESSORY = 'accesorio';
    case FIGURE = 'figura';

    public function label(): string
    {
        return match ($this) {
            self::BOOK => 'Libros',
            self::STATIONERY => 'Papelería',
            self::ACCESSORY => 'Accesorios',
            self::FIGURE => 'Figuras',
        };
    }

    public function isBook(): bool
    {
        return $this === self::BOOK;
    }
}
