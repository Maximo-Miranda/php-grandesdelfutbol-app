<?php

namespace App\Services;

use App\Enums\ReelStatus;
use App\Jobs\GenerateMatchReel;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Notifications\MatchReelsReadyNotification;
use Illuminate\Support\Facades\Bus;

class ReelService
{
    public function generateReelsForMatch(FootballMatch $match): void
    {
        if (! $match->youtube_url) {
            return;
        }

        $events = $match->events()->whereNotNull('player_id')->get();

        $jobs = [];

        foreach ($events as $event) {
            $clipWindow = $this->calculateClipWindow(
                $event->minute,
                $event->second,
                $match->video_offset_seconds ?? 0,
            );

            $reel = MatchReel::create([
                'match_id' => $match->id,
                'event_id' => $event->id,
                'player_id' => $event->player_id,
                'status' => ReelStatus::Pending,
                'title' => sprintf(
                    '%s — %s (%d:%02d)',
                    $event->player?->display_name ?? 'Evento',
                    $event->event_type->label(),
                    $event->minute,
                    $event->second,
                ),
                'start_second' => $clipWindow['start'],
                'end_second' => $clipWindow['end'],
                'duration' => $clipWindow['end'] - $clipWindow['start'],
            ]);

            $jobs[] = new GenerateMatchReel($reel);
        }

        if ($jobs !== []) {
            Bus::batch($jobs)
                ->name("reels-match-{$match->id}")
                ->onQueue('reels')
                ->then(function () use ($match) {
                    $match->club?->owner?->notify(new MatchReelsReadyNotification($match));
                })
                ->allowFailures()
                ->dispatch();
        }
    }

    /** @return array{start: int, end: int} */
    public function calculateClipWindow(int $eventMinute, int $eventSecond, int $videoOffset = 0): array
    {
        $eventTotalSeconds = ($eventMinute * 60) + $eventSecond + $videoOffset;

        $start = max(0, $eventTotalSeconds - 10);
        $end = $eventTotalSeconds + 15;

        return [
            'start' => $start,
            'end' => $end,
        ];
    }
}
