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

        return (new MailMessage)
            ->subject("You've been invited to join {$club->name}")
            ->line("You've been invited to join the club \"{$club->name}\".")
            ->action('Accept Invitation', url("/clubs/invitations/{$this->invitation->token}/accept"))
            ->line('This invitation expires in 7 days.');
    }
}
