<?php

namespace App\Notifications;

use App\Channels\NtfyChannel;
use App\Models\FootballMatch;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;

class MatchStatsFinalizedNotification extends Notification implements ShouldQueue
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

    /** @return array<int, object> */
    public function middleware(object $notifiable, string $channel): array
    {
        return [new RateLimited('ntfy')];
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [NtfyChannel::class];
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        return NtfyMessage::create("{$this->matchTitle} — Revisa tus números")
            ->title('Estadísticas disponibles')
            ->tags('trophy')
            ->priority(3)
            ->click($this->summaryUrl);
    }
}
