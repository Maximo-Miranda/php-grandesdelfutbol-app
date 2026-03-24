<?php

namespace App\Services;

use App\Enums\MatchEventType;
use App\Enums\ReelSource;
use App\Enums\ReelStatus;
use App\Enums\VideoUploadStatus;
use App\Jobs\GenerateMatchReel;
use App\Models\FootballMatch;
use App\Models\MatchReel;
use App\Models\Player;
use App\Models\User;
use App\Notifications\MatchReelsReadyNotification;
use Illuminate\Support\Facades\Bus;

class ReelService
{
    public function generateReelsForMatch(FootballMatch $match, bool $force = false): void
    {
        $videoUpload = $match->videoUpload;

        if (! $videoUpload || $videoUpload->status !== VideoUploadStatus::Ready) {
            return;
        }

        if ($force) {
            $this->deleteAutoReels($match);
        }

        $events = $match->events()
            ->where(function ($q) {
                $q->whereIn('event_type', [MatchEventType::Goal, MatchEventType::PenaltyScored])
                    ->orWhere('highlighted', true);
            })
            ->whereNotNull('player_id')
            ->get();

        $this->removeOrphanedAutoReels($match, $events->pluck('id')->all());

        $jobs = [];

        foreach ($events as $event) {
            $clipWindow = $this->calculateClipWindow(
                $event->minute,
                $event->second,
                $videoUpload->video_offset_seconds ?? 0,
            );

            $existingReel = $match->reels()
                ->where('event_id', $event->id)
                ->where('source', ReelSource::Auto)
                ->first();

            if ($this->shouldSkipExistingReel($existingReel, $clipWindow)) {
                continue;
            }

            $this->clearAndDeleteReel($existingReel);

            $reel = $this->createReel($match, [
                'event_id' => $event->id,
                'player_id' => $event->player_id,
                'source' => ReelSource::Auto,
                'title' => sprintf(
                    '%s — %s (%d:%02d)',
                    $event->player?->display_name ?? 'Evento',
                    $event->event_type->label(),
                    $event->minute,
                    $event->second,
                ),
            ], $clipWindow);

            $jobs[] = new GenerateMatchReel($reel);
        }

        if ($jobs === []) {
            return;
        }

        Bus::batch($jobs)
            ->name("reels-match-{$match->id}")
            ->onQueue('reels')
            ->then(fn () => $match->club?->owner?->notify(new MatchReelsReadyNotification($match)))
            ->allowFailures()
            ->dispatch();
    }

    /** @param array{title?: string|null, minute: int, second: int, player_id?: int|null, request_notes?: string|null} $data */
    public function createManualClip(FootballMatch $match, array $data): MatchReel
    {
        $clipWindow = $this->clipWindowForMatch($match, $data['minute'], $data['second']);
        $player = Player::find($data['player_id'] ?? null);

        $playerSuffix = $player ? " — {$player->display_name}" : '';
        $title = $data['title'] ?? sprintf('Reel %d:%02d%s', $data['minute'], $data['second'], $playerSuffix);

        return $this->createAndDispatchReel($match, [
            'player_id' => $player?->id,
            'requested_by' => $player?->user_id,
            'source' => ReelSource::Manual,
            'title' => $title,
            'request_notes' => $data['request_notes'] ?? null,
        ], $clipWindow);
    }

    /** @param array{minute: int, second: int, request_notes?: string|null} $data */
    public function createMatchClip(FootballMatch $match, array $data): MatchReel
    {
        $clipWindow = $this->clipWindowForMatch($match, $data['minute'], $data['second']);

        return $this->createAndDispatchReel($match, [
            'source' => ReelSource::Request,
            'title' => sprintf('Reel %d:%02d', $data['minute'], $data['second']),
            'request_notes' => $data['request_notes'] ?? null,
        ], $clipWindow);
    }

    /** @param array{minute: int, second: int, request_notes?: string|null} $data */
    public function createPlayerClip(FootballMatch $match, User $user, array $data): MatchReel
    {
        $player = $match->club?->players()->where('user_id', $user->id)->first();
        $clipWindow = $this->clipWindowForMatch($match, $data['minute'], $data['second']);

        return $this->createAndDispatchReel($match, [
            'player_id' => $player?->id,
            'requested_by' => $user->id,
            'source' => ReelSource::Request,
            'title' => sprintf('Reel - %s (%d:%02d)', $user->name, $data['minute'], $data['second']),
            'request_notes' => $data['request_notes'] ?? null,
        ], $clipWindow);
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

        return [
            'start' => max(0, $eventTotalSeconds - 15),
            'end' => $eventTotalSeconds + 10,
        ];
    }

    public function deleteAutoReels(FootballMatch $match): void
    {
        $match->reels()
            ->where('source', ReelSource::Auto)
            ->where('status', '!=', ReelStatus::Processing)
            ->each(fn (MatchReel $reel) => $this->clearAndDeleteReel($reel));
    }

    public function fetchVideoDuration(FootballMatch $match): void
    {
        // Duration is stored in the video upload record.
        // No-op: kept for interface compatibility.
    }

    /** @return array{start: int, end: int} */
    private function clipWindowForMatch(FootballMatch $match, int $minute, int $second): array
    {
        $offset = $match->videoUpload?->video_offset_seconds ?? 0;

        return $this->calculateClipWindow($minute, $second, $offset);
    }

    /** @param array<string, mixed> $attributes */
    private function createReel(FootballMatch $match, array $attributes, array $clipWindow): MatchReel
    {
        return MatchReel::create([
            'match_id' => $match->id,
            'status' => ReelStatus::Pending,
            'start_second' => $clipWindow['start'],
            'end_second' => $clipWindow['end'],
            'duration' => $clipWindow['end'] - $clipWindow['start'],
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createAndDispatchReel(FootballMatch $match, array $attributes, array $clipWindow): MatchReel
    {
        $reel = $this->createReel($match, $attributes, $clipWindow);

        GenerateMatchReel::dispatch($reel);

        return $reel;
    }

    /** @param array<int> $qualifyingEventIds */
    private function removeOrphanedAutoReels(FootballMatch $match, array $qualifyingEventIds): void
    {
        $match->reels()
            ->where('source', ReelSource::Auto)
            ->where('status', '!=', ReelStatus::Processing)
            ->where(function ($q) use ($qualifyingEventIds) {
                $q->whereNull('event_id')
                    ->orWhereNotIn('event_id', $qualifyingEventIds);
            })
            ->each(fn (MatchReel $reel) => $this->clearAndDeleteReel($reel));
    }

    /** @param array{start: int, end: int} $clipWindow */
    private function shouldSkipExistingReel(?MatchReel $reel, array $clipWindow): bool
    {
        if (! $reel) {
            return false;
        }

        $timesMatch = $reel->start_second === $clipWindow['start']
            && $reel->end_second === $clipWindow['end'];

        return $timesMatch && $reel->status !== ReelStatus::Failed;
    }

    private function clearAndDeleteReel(?MatchReel $reel): void
    {
        if (! $reel) {
            return;
        }

        $reel->clearMediaCollection('reel');
        $reel->delete();
    }
}
