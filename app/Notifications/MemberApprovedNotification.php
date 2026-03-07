<?php

namespace App\Notifications;

use App\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Club $club) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Bienvenido a {$this->club->name}!")
            ->greeting("Hola {$notifiable->name}!")
            ->line("Tu solicitud para unirte al club \"{$this->club->name}\" ha sido aprobada.")
            ->line('Ya puedes ver los partidos y participar con tu equipo.')
            ->action('Ir al club', url("/clubs/{$this->club->ulid}"));
    }
}
