<?php

namespace App\Services;

use App\Enums\MatchEventType;
use App\Enums\ReelSource;
use App\Enums\ReelStatus;
use App\Jobs\GenerateMatchReel;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Models\Player;
use App\Models\User;
use App\Notifications\MatchReelsReadyNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;

class ReelService
{
    public function generateReelsForMatch(FootballMatch $match): void
    {
        if (! $match->youtube_url) {
            return;
        }

        $this->fetchVideoDuration($match);

        $events = $match->events()
            ->where(function ($q) {
                $q->whereIn('event_type', [MatchEventType::Goal, MatchEventType::PenaltyScored])
                    ->orWhere('highlighted', true);
            })
            ->whereNotNull('player_id')
            ->get();

        $jobs = [];

        foreach ($events as $event) {
            if ($match->reels()->where('event_id', $event->id)->exists()) {
                continue;
            }

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
                'source' => ReelSource::Auto,
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

    /** @param array{title?: string|null, minute: int, second: int, player_id?: int|null, request_notes?: string|null} $data */
    public function createManualClip(FootballMatch $match, array $data): MatchReel
    {
        $clipWindow = $this->calculateClipWindow(
            $data['minute'],
            $data['second'],
            $match->video_offset_seconds ?? 0,
        );

        $playerId = $data['player_id'] ?? null;
        $player = $playerId ? Player::find($playerId) : null;

        $title = $data['title'] ?? sprintf(
            'Reel %d:%02d%s',
            $data['minute'],
            $data['second'],
            $player ? " — {$player->display_name}" : '',
        );

        $reel = MatchReel::create([
            'match_id' => $match->id,
            'player_id' => $playerId,
            'requested_by' => $player?->user_id,
            'status' => ReelStatus::Pending,
            'source' => ReelSource::Manual,
            'title' => $title,
            'start_second' => $clipWindow['start'],
            'end_second' => $clipWindow['end'],
            'duration' => $clipWindow['end'] - $clipWindow['start'],
            'request_notes' => $data['request_notes'] ?? null,
        ]);

        GenerateMatchReel::dispatch($reel);

        return $reel;
    }

    /** @param array{minute: int, second: int, request_notes?: string|null} $data */
    public function createMatchClip(FootballMatch $match, array $data): MatchReel
    {
        $clipWindow = $this->calculateClipWindow(
            $data['minute'],
            $data['second'],
            $match->video_offset_seconds ?? 0,
        );

        $reel = MatchReel::create([
            'match_id' => $match->id,
            'status' => ReelStatus::Pending,
            'source' => ReelSource::Request,
            'title' => sprintf('Reel %d:%02d', $data['minute'], $data['second']),
            'start_second' => $clipWindow['start'],
            'end_second' => $clipWindow['end'],
            'duration' => $clipWindow['end'] - $clipWindow['start'],
            'request_notes' => $data['request_notes'] ?? null,
        ]);

        GenerateMatchReel::dispatch($reel);

        return $reel;
    }

    /** @param array{minute: int, second: int, request_notes?: string|null} $data */
    public function createPlayerClip(FootballMatch $match, User $user, array $data): MatchReel
    {
        $player = $match->club?->players()->where('user_id', $user->id)->first();

        $clipWindow = $this->calculateClipWindow(
            $data['minute'],
            $data['second'],
            $match->video_offset_seconds ?? 0,
        );

        $reel = MatchReel::create([
            'match_id' => $match->id,
            'player_id' => $player?->id,
            'requested_by' => $user->id,
            'status' => ReelStatus::Pending,
            'source' => ReelSource::Request,
            'title' => sprintf(
                'Reel - %s (%d:%02d)',
                $user->name,
                $data['minute'],
                $data['second'],
            ),
            'start_second' => $clipWindow['start'],
            'end_second' => $clipWindow['end'],
            'duration' => $clipWindow['end'] - $clipWindow['start'],
            'request_notes' => $data['request_notes'] ?? null,
        ]);

        GenerateMatchReel::dispatch($reel);

        return $reel;
    }

    public function approveClipRequest(MatchReel $reel): void
    {
        $reel->update(['status' => ReelStatus::Pending]);

        GenerateMatchReel::dispatch($reel);
    }

    public function rejectClipRequest(MatchReel $reel): void
    {
        $reel->delete();
    }

    /** @return array{start: int, end: int} */
    public function calculateClipWindow(int $eventMinute, int $eventSecond, int $videoOffset = 0): array
    {
        $eventTotalSeconds = ($eventMinute * 60) + $eventSecond + $videoOffset;

        $start = max(0, $eventTotalSeconds - 15);
        $end = $eventTotalSeconds + 10;

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    public function fetchVideoDuration(FootballMatch $match): void
    {
        if ($match->video_duration_seconds || ! $match->youtube_url) {
            return;
        }

        try {
            $result = Process::timeout(30)->run([
                'yt-dlp',
                '--print', 'duration',
                '--no-download',
                $match->youtube_url,
            ]);

            if ($result->successful()) {
                $duration = (int) trim($result->output());

                if ($duration > 0) {
                    $match->update(['video_duration_seconds' => $duration]);
                }
            }
        } catch (\Throwable) {
            // Non-critical — slider will fallback to match duration
        }
    }
}
