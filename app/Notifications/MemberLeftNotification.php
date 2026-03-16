<?php

namespace App\Notifications;

use App\Models\Club;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberLeftNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Club $club,
        public User $member,
    ) {
        $this->onQueue('notifications');
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->member->name} salió de {$this->club->name}")
            ->greeting("Hola {$notifiable->name}!")
            ->line("{$this->member->name} ha decidido salir del club \"{$this->club->name}\".")
            ->action('Ver miembros', url("/clubs/{$this->club->ulid}/members"));
    }
}
