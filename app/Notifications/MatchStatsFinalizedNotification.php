<?php

namespace App\Notifications;

use App\Channels\NtfyChannel;
use App\Models\FootballMatch;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MatchStatsFinalizedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $matchTitle;

    private string $summaryUrl;

    public function __construct(public FootballMatch $match)
    {
        $this->onQueue('notifications');
        $this->matchTitle = $match->title;
        $this->summaryUrl = route('clubs.matches.summary', [$match->club, $match]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [NtfyChannel::class];
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        return NtfyMessage::create("Las estadísticas de {$this->matchTitle} ya están disponibles")
            ->title('Estadísticas registradas')
            ->tags('soccer,bar_chart')
            ->priority(3)
            ->click($this->summaryUrl);
    }
}
