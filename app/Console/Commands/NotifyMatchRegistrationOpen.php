<?php

namespace App\Console\Commands;

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
            ->with('club')
            ->where('status', MatchStatus::Upcoming)
            ->whereNull('registration_notified_at');

        // Use Laravel's now() (app.timezone) instead of DB now() (UTC) to avoid timezone mismatch
        if ($driver === 'pgsql') {
            $query->whereRaw("? >= COALESCE(registration_opens_at, scheduled_at - (registration_opens_hours || ' hours')::interval)", [$now]);
        } else {
            $query->whereRaw("? >= COALESCE(registration_opens_at, datetime(scheduled_at, '-' || registration_opens_hours || ' hours'))", [$now]);
        }

        $query->chunkById(100, function ($matches) {
            foreach ($matches as $match) {
                $users = $match->club->approvedMemberUsers();

                if ($users->isNotEmpty()) {
                    Notification::send($users, new MatchRegistrationOpenNotification($match));
                    $this->info("Match #{$match->id} '{$match->title}': notified {$users->count()} member(s).");
                }

                Log::info('matches:notify-registration-open — notified', [
                    'match_id' => $match->id,
                    'member_count' => $users->count(),
                ]);

                $match->update(['registration_notified_at' => now()]);
            }
        });

        return self::SUCCESS;
    }
}
