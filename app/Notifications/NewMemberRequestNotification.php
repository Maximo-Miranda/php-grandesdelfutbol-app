<?php

namespace App\Notifications;

use App\Models\Club;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMemberRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Club $club,
        public User $requester,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nueva solicitud en {$this->club->name}")
            ->greeting("Hola {$notifiable->name}!")
            ->line("{$this->requester->name} quiere unirse al club \"{$this->club->name}\".")
            ->line('Revisa las solicitudes pendientes para aprobar o rechazar.')
            ->action('Ver miembros', url("/clubs/{$this->club->ulid}/members"));
    }
}
