<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail sent when an order is placed: what was bought, the total and the link to follow it and pay. */
class OrderPlaced extends Notification
{
    public function __construct(public readonly Order $order) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Recibimos tu pedido {$this->order->tracking_code}")
            ->greeting('¡Gracias por tu compra!')
            ->line("Recibimos tu pedido **{$this->order->tracking_code}**. Queda reservado cuando se confirme el pago.");

        foreach ($this->order->items as $item) {
            $mail->line("{$item->quantity} × ".($item->product_snapshot['name'] ?? 'Producto').' — '.Money::format($item->lineTotal()));
        }

        return $mail
            ->line('Envío: '.((float) $this->order->shipping_cost > 0 ? Money::format($this->order->shipping_cost) : 'Gratis'))
            ->line('**Total: '.Money::format($this->order->total).'**')
            ->action('Ver y pagar mi pedido', $this->order->signedUrl())
            ->line('Si no hiciste este pedido, ignora este mensaje.');
    }
}
