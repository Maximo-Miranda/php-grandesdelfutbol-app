<?php

namespace App\Notifications;

use App\Models\FootballMatch;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class MatchRegistrationOpenNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Convocatoria abierta')
            ->body("{$this->matchTitle} — Confirma tu asistencia")
            ->icon('/pwa-192x192.png')
            ->badge('/pwa-192x192.png')
            ->tag("match-registration-{$this->match->id}")
            ->data(['url' => $this->matchUrl]);
    }

    /** @return array<string, mixed> */
    public function toNtfyPayload(): array
    {
        return NtfyMessage::create("{$this->matchTitle} — Confirma tu asistencia")
            ->title('Convocatoria abierta')
            ->tags('soccer')
            ->priority(4)
            ->click($this->matchUrl)
            ->toArray();
    }
}
