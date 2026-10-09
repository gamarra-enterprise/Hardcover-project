<?php

namespace App\Notifications;

use App\Models\NewsletterSubscriber;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewsletterWelcome extends Notification
{
    public function __construct(public readonly NewsletterSubscriber $subscriber) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bienvenido al Club de lectores Bookery')
            ->greeting('¡Hola, lector!')
            ->line('Ya estás en la lista: te escribiremos con una recomendación a la semana y avisos de novedades.')
            ->action('Ver novedades', route('collection.novedades'))
            ->line('Si no fuiste tú, o ya no quieres recibir estos correos, puedes salir de la lista cuando quieras: '.$this->subscriber->unsubscribeUrl());
    }
}
