<?php

namespace App\Notifications;

use App\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberRemovedNotification extends Notification implements ShouldQueue
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
            ->subject("Has sido removido de {$this->club->name}")
            ->greeting("Hola {$notifiable->name}!")
            ->line("Un administrador te ha removido del club \"{$this->club->name}\".")
            ->line('Si crees que fue un error, puedes comunicarte con los administradores del club.');
    }
}
