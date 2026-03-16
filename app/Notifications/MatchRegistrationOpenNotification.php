<?php

namespace App\Notifications;

use App\Channels\NtfyChannel;
use App\Models\FootballMatch;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MatchRegistrationOpenNotification extends Notification implements ShouldQueue
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
        $matchUrl = url("/clubs/{$match->club->ulid}/matches/{$match->ulid}");

        return NtfyMessage::create("{$match->title} abrió la convocatoria")
            ->title('Confirma tu asistencia')
            ->tags('soccer,calendar')
            ->priority(4)
            ->click($matchUrl);
    }
}
