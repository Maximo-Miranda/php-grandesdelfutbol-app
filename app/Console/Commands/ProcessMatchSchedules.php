<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Services\MatchService;
use Illuminate\Console\Command;

class ProcessMatchSchedules extends Command
{
    protected $signature = 'matches:process-schedules';

    protected $description = 'Auto-inicia partidos programados cuya hora ya pasó y auto-completa partidos iniciados por el sistema cuya duración ya terminó';

    public function __construct(private readonly MatchService $matchService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cancelled = $this->autoCancelMatches();
        $started = $this->autoStartMatches();
        [$completed, $recreated] = $this->autoCompleteMatches();

        $this->info("Partidos cancelados: {$cancelled}, iniciados: {$started}, completados: {$completed}, recreados: {$recreated}");

        return self::SUCCESS;
    }

    private function autoCancelMatches(): int
    {
        $matches = FootballMatch::query()
            ->with('field')
            ->where('status', MatchStatus::Upcoming)
            ->where('auto_cancel', true)
            ->where('scheduled_at', '>', now())
            ->withCount(['attendances as confirmed_count' => function ($query) {
                $query->where('status', AttendanceStatus::Confirmed);
            }])
            ->get()
            ->filter(function (FootballMatch $match) {
                $cancelWindow = now()->addHours($match->effectiveCancelHoursBefore());

                return $match->scheduled_at->lte($cancelWindow)
                    && $match->confirmed_count < $match->min_players_required;
            });

        $cancelled = 0;

        foreach ($matches as $match) {
            $affected = FootballMatch::query()
                ->where('id', $match->id)
                ->where('status', MatchStatus::Upcoming)
                ->update(['status' => MatchStatus::Cancelled]);

            if ($affected === 0) {
                continue;
            }

            $cancelled++;
            $match->status = MatchStatus::Cancelled;

            $this->matchService->recreateIfRecurring($match);
        }

        return $cancelled;
    }

    private function autoStartMatches(): int
    {
        $matches = FootballMatch::query()
            ->where('status', MatchStatus::Upcoming)
            ->where('scheduled_at', '<=', now())
            ->get();

        $started = 0;

        foreach ($matches as $match) {
            $affected = FootballMatch::query()
                ->where('id', $match->id)
                ->where('status', MatchStatus::Upcoming)
                ->update([
                    'status' => MatchStatus::InProgress,
                    'auto_started' => true,
                    'started_at' => $match->scheduled_at,
                ]);

            if ($affected > 0) {
                $started++;
            }
        }

        return $started;
    }

    /** @return array{int, int} */
    private function autoCompleteMatches(): array
    {
        $matches = FootballMatch::query()
            ->with('field')
            ->where('status', MatchStatus::InProgress)
            ->where('auto_started', true)
            ->whereNotNull('started_at')
            ->get()
            ->filter(fn (FootballMatch $match) => now()->gte(
                $match->started_at->copy()->addMinutes($match->duration_minutes),
            ));

        $completed = 0;
        $recreated = 0;

        foreach ($matches as $match) {
            $affected = FootballMatch::query()
                ->where('id', $match->id)
                ->where('status', MatchStatus::InProgress)
                ->update([
                    'status' => MatchStatus::Completed,
                    'ended_at' => $match->started_at->copy()->addMinutes($match->duration_minutes),
                ]);

            if ($affected === 0) {
                continue;
            }

            $completed++;
            $match->status = MatchStatus::Completed;

            if ($this->matchService->recreateIfRecurring($match)) {
                $recreated++;
            }
        }

        return [$completed, $recreated];
    }
}
