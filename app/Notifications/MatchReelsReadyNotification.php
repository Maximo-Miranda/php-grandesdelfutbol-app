<?php

namespace App\Notifications;

use App\Models\FootballMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MatchReelsReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FootballMatch $match,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reels_ready',
            'match_id' => $this->match->id,
            'match_title' => $this->match->title,
            'message' => "Los reels del partido \"{$this->match->title}\" están listos.",
        ];
    }
}
