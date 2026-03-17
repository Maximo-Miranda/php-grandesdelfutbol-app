<?php

namespace App\Console\Commands;

use App\Enums\ClubMemberStatus;
use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Notifications\MatchRegistrationOpenNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyMatchRegistrationOpen extends Command
{
    protected $signature = 'matches:notify-registration-open';

    protected $description = 'Notifica a los miembros del club cuando se abre la convocatoria de un partido';

    public function handle(): int
    {
        $driver = DB::getDriverName();
        $now = now()->toDateTimeString();

        $query = FootballMatch::query()
            ->where('status', MatchStatus::Upcoming)
            ->whereNull('registration_notified_at');

        // Use Laravel's now() (app.timezone) instead of DB now() (UTC) to avoid timezone mismatch
        if ($driver === 'pgsql') {
            $query->whereRaw("? >= scheduled_at - (registration_opens_hours || ' hours')::interval", [$now]);
        } else {
            // SQLite fallback for tests
            $query->whereRaw("? >= datetime(scheduled_at, '-' || registration_opens_hours || ' hours')", [$now]);
        }

        if (! $query->exists()) {
            $this->info('No eligible matches found.');

            return self::SUCCESS;
        }

        $query->chunkById(100, function ($matches) {
            foreach ($matches as $match) {
                $users = $match->club
                    ->members()
                    ->where('status', ClubMemberStatus::Approved)
                    ->whereHas('user', fn ($q) => $q->whereNotNull('ntfy_enabled_at'))
                    ->with('user')
                    ->get()
                    ->pluck('user');

                if ($users->isEmpty()) {
                    $this->info("Match #{$match->id} '{$match->title}': no eligible users with ntfy enabled.");
                    Log::info('matches:notify-registration-open — no eligible users', ['match_id' => $match->id]);
                } else {
                    Notification::send($users, new MatchRegistrationOpenNotification($match));
                    $this->info("Match #{$match->id} '{$match->title}': notified {$users->count()} user(s).");
                    Log::info('matches:notify-registration-open — notified', [
                        'match_id' => $match->id,
                        'user_count' => $users->count(),
                    ]);
                }

                $match->update(['registration_notified_at' => now()]);
            }
        });

        return self::SUCCESS;
    }
}
