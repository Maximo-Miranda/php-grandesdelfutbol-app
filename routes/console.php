<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telescope:prune --hours=48')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('video-uploads:cleanup --hours=48')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('app:cleanup-match-videos --days=30')->weekly()->withoutOverlapping()->onOneServer();
Schedule::command('youtube:process-pending')->hourly()->withoutOverlapping()->onOneServer();
