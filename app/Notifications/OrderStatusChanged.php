<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail to the customer each time their order changes status. */
class OrderStatusChanged extends Notification
{
    public function __construct(public readonly Order $order, public readonly OrderStatus $status, public readonly ?string $reason = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Tu pedido {$this->order->tracking_code}: {$this->status->label()}")
            ->greeting('¡Hola!')
            ->line("Tu pedido **{$this->order->tracking_code}** ahora está: **{$this->status->label()}**.")
            ->line($this->detail());

        if ($this->reason) {
            $mail->line($this->reason);
        }

        if ($this->status === OrderStatus::CANCELLED && $this->order->refund_amount !== null) {
            $mail->line('Te devolveremos '.Money::format($this->order->refund_amount).' por el mismo medio de pago.');
        }

        return $mail->line('Guarda este código para consultar tu pedido.');
    }

    private function detail(): string
    {
        return match ($this->status) {
            OrderStatus::CONFIRMED => 'Recibimos tu pago. Empezaremos a preparar tu pedido.',
            OrderStatus::PROCESSING => 'Estamos preparando tus libros.',
            OrderStatus::SHIPPED => 'Tu pedido ya salió y va en camino.',
            OrderStatus::DELIVERED => '¡Que lo disfrutes! Gracias por comprar en Hardcover Bookery.',
            OrderStatus::CANCELLED => 'Tu pedido fue cancelado.',
            OrderStatus::REFUNDED => 'Ya realizamos el reembolso de tu pedido.',
            default => 'Estamos atendiendo tu pedido.',
        };
    }
}
