<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCode extends Notification implements ShouldQueue
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
            ->subject('Tu código de verificación')
            ->greeting('Hola!')
            ->line('Tu código de verificación es:')
            ->line("**{$this->code}**")
            ->line('Este código expira en 10 minutos.')
            ->line('Si no solicitaste esto, ignora este correo.');
    }
}
