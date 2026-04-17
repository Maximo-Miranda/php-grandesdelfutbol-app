<?php

namespace App\Notifications;

use App\Models\FootballMatch;
use App\Notifications\Concerns\SendsMailAndWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class MatchStatsFinalizedNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsMailAndWebPush;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    private string $matchTitle;

    private string $summaryUrl;

    public function __construct(public FootballMatch $match)
    {
        $this->onQueue('notifications');
        $this->afterCommit();
        $this->matchTitle = $match->title;
        $this->summaryUrl = route('clubs.matches.summary', [$match->club, $match]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Estadísticas disponibles — {$this->matchTitle}")
            ->greeting('Estadísticas registradas')
            ->line("Las estadísticas de **{$this->matchTitle}** ya están disponibles. Revisa tus números.")
            ->action('Ver resumen', $this->summaryUrl)
            ->salutation('Grandes del Futbol');
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Estadísticas disponibles')
            ->body("{$this->matchTitle} — Revisa tus números")
            ->icon('/pwa-192x192.png')
            ->badge('/badge-96x96.png')
            ->tag("match-stats-{$this->match->id}")
            ->data(['url' => $this->summaryUrl]);
    }
}
