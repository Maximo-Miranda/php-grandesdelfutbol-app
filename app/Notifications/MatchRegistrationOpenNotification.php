<?php

namespace App\Notifications;

use App\Models\FootballMatch;
use App\Notifications\Concerns\SendsMailAndWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class MatchRegistrationOpenNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsMailAndWebPush;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    private string $matchTitle;

    private string $matchUrl;

    public function __construct(public FootballMatch $match)
    {
        $this->onQueue('notifications');
        $this->afterCommit();
        $this->matchTitle = $match->title;
        $this->matchUrl = route('clubs.matches.show', [$match->club, $match]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Convocatoria abierta — {$this->matchTitle}")
            ->greeting('Se abrió la convocatoria')
            ->line("**{$this->matchTitle}** ya tiene convocatoria abierta. Confirma tu asistencia.")
            ->action('Confirmar asistencia', $this->matchUrl)
            ->salutation('Grandes del Futbol');
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Convocatoria abierta')
            ->body("{$this->matchTitle} — Confirma tu asistencia")
            ->icon('/pwa-192x192.png')
            ->badge('/badge-96x96.png')
            ->tag("match-registration-{$this->match->id}")
            ->data(['url' => $this->matchUrl]);
    }
}
