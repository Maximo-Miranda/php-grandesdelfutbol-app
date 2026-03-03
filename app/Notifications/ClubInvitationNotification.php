<?php

namespace App\Notifications;

use App\Models\ClubInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ClubInvitation $invitation) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $club = $this->invitation->club;
        $inviter = $this->invitation->inviter;
        $inviterName = $inviter?->name ?? 'Un administrador';

        return (new MailMessage)
            ->subject("Te han invitado a {$club->name}")
            ->greeting('Hola!')
            ->line("{$inviterName} te ha invitado a unirte al club \"{$club->name}\".")
            ->line('Acepta la invitacion para empezar a jugar con tu equipo.')
            ->action('Aceptar invitacion', route('invitations.show', $this->invitation->token))
            ->line('Esta invitacion expira en 7 dias.');
    }
}
