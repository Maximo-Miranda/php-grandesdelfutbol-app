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

    public function __construct(public FootballMatch $match)
    {
        $this->onQueue('notifications');
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [NtfyChannel::class];
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        $match = $this->match;
        $summaryUrl = url("/clubs/{$match->club->ulid}/matches/{$match->ulid}/summary");

        return NtfyMessage::create("Las estadísticas de {$match->title} ya están disponibles")
            ->title('Estadísticas registradas')
            ->tags('soccer,bar_chart')
            ->priority(3)
            ->click($summaryUrl);
    }
}
