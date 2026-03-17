<?php

namespace App\Notifications;

use App\Channels\NtfyChannel;
use App\Models\FootballMatch;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;

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
        return NtfyMessage::create("{$this->matchTitle} — Confirma tu asistencia")
            ->title('Convocatoria abierta')
            ->tags('soccer')
            ->priority(4)
            ->click($this->matchUrl);
    }
}
