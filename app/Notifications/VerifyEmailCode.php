<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCode extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu codigo de verificacion')
            ->greeting('Hola!')
            ->line('Tu codigo de verificacion es:')
            ->line("**{$this->code}**")
            ->line('Este codigo expira en 10 minutos.')
            ->line('Si no solicitaste esto, ignora este correo.');
    }
}
