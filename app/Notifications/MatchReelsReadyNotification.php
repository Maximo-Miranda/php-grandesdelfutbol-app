<?php

namespace App\Notifications;

use App\Models\FootballMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MatchReelsReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $matchTitle;

    private string $summaryUrl;

    public function __construct(
        public FootballMatch $match,
    ) {
        $this->onQueue('notifications');
        $this->matchTitle = $match->title;
        $this->summaryUrl = route('clubs.matches.summary', [$match->club, $match]);
    }

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Reels listos — {$this->matchTitle}")
            ->greeting('¡Tus reels están listos!')
            ->line("Los reels del partido \"{$this->matchTitle}\" se han generado correctamente.")
            ->action('Ver reels', $this->summaryUrl)
            ->salutation('Grandes del Fútbol');
    }
}
