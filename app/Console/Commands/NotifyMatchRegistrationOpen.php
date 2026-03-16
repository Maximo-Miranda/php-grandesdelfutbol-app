<?php

namespace App\Console\Commands;

use App\Enums\ClubMemberStatus;
use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Notifications\MatchRegistrationOpenNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class NotifyMatchRegistrationOpen extends Command
{
    protected $signature = 'matches:notify-registration-open';

    protected $description = 'Notifica a los miembros del club cuando se abre la convocatoria de un partido';

    public function handle(): int
    {
        $driver = DB::getDriverName();

        $query = FootballMatch::query()
            ->where('status', MatchStatus::Upcoming)
            ->whereNull('registration_notified_at');

        // Filter in DB: now() >= scheduled_at - registration_opens_hours
        if ($driver === 'pgsql') {
            $query->whereRaw("now() >= scheduled_at - (registration_opens_hours || ' hours')::interval");
        } else {
            // SQLite fallback for tests
            $query->whereRaw("datetime('now') >= datetime(scheduled_at, '-' || registration_opens_hours || ' hours')");
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

                Notification::send($users, new MatchRegistrationOpenNotification($match));

                $match->update(['registration_notified_at' => now()]);
            }
        });

        return self::SUCCESS;
    }
}
