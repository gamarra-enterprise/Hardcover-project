<?php

namespace App\Enums;

/**
 * Life of an order. Stages follow the AxisLab idea (confirmed, in preparation, on its way,
 * delivered) adapted to a bookshop, and an order can only move along the allowed transitions.
 */
enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente de pago',
            self::CONFIRMED => 'Confirmado',
            self::PROCESSING => 'En preparación',
            self::SHIPPED => 'Enviado',
            self::DELIVERED => 'Entregado',
            self::CANCELLED => 'Cancelado',
            self::REFUNDED => 'Reembolsado',
        };
    }

    /** @return list<self> statuses an order in this one may move to */
    public function next(): array
    {
        return match ($this) {
            self::PENDING => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED],
            self::DELIVERED => [self::REFUNDED],
            self::CANCELLED => [self::REFUNDED],
            self::REFUNDED => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    /** Statuses in which the customer can still cancel on their own. Once it ships, it cannot be cancelled. */
    public function customerCanCancel(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED, self::PROCESSING], true);
    }

    /** The stages shown as a timeline to the customer. */
    public static function timeline(): array
    {
        return [self::PENDING, self::CONFIRMED, self::PROCESSING, self::SHIPPED, self::DELIVERED];
    }
}
