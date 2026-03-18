<?php

namespace App\Notifications;

use App\Models\FootballMatch;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class MatchVideoUploadedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Resumen del partido')
            ->body("{$this->matchTitle} — Video disponible")
            ->icon('/pwa-192x192.png')
            ->badge('/badge-96x96.png')
            ->tag("match-video-{$this->match->id}")
            ->data(['url' => $this->summaryUrl]);
    }

    /** @return array<string, mixed> */
    public function toNtfyPayload(): array
    {
        return NtfyMessage::create("{$this->matchTitle} — Video disponible")
            ->title('Resumen del partido')
            ->tags('clapper')
            ->priority(3)
            ->click($this->summaryUrl)
            ->toArray();
    }
}
