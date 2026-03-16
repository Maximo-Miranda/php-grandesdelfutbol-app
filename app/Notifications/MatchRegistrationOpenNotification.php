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

    private string $matchTitle;

    private string $matchUrl;

    public function __construct(public FootballMatch $match)
    {
        $this->onQueue('notifications');
        $this->matchTitle = $match->title;
        $this->matchUrl = route('clubs.matches.show', [$match->club, $match]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [NtfyChannel::class];
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        return NtfyMessage::create("{$this->matchTitle} abrió la convocatoria")
            ->title('Confirma tu asistencia')
            ->tags('soccer,calendar')
            ->priority(4)
            ->click($this->matchUrl);
    }
}
