<?php

namespace App\Console\Commands;

use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use Illuminate\Console\Command;

class ProcessMatchSchedules extends Command
{
    protected $signature = 'matches:process-schedules';

    protected $description = 'Auto-inicia partidos programados cuya hora ya pasó y auto-completa partidos iniciados por el sistema cuya duración ya terminó';

    public function handle(): int
    {
        $started = $this->autoStartMatches();
        $completed = $this->autoCompleteMatches();

        $this->info("Partidos iniciados: {$started}, completados: {$completed}");

        return self::SUCCESS;
    }

    private function autoStartMatches(): int
    {
        $matches = FootballMatch::query()
            ->where('status', MatchStatus::Upcoming)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($matches as $match) {
            $match->update([
                'status' => MatchStatus::InProgress,
                'auto_started' => true,
                'started_at' => $match->scheduled_at,
            ]);
        }

        return $matches->count();
    }

    private function autoCompleteMatches(): int
    {
        $matches = FootballMatch::query()
            ->where('status', MatchStatus::InProgress)
            ->where('auto_started', true)
            ->whereNotNull('started_at')
            ->get()
            ->filter(function (FootballMatch $match) {
                $endTime = $match->started_at->copy()->addMinutes($match->duration_minutes);

                return now()->gte($endTime);
            });

        foreach ($matches as $match) {
            $match->update([
                'status' => MatchStatus::Completed,
                'ended_at' => $match->started_at->copy()->addMinutes($match->duration_minutes),
            ]);
        }

        return $matches->count();
    }
}
