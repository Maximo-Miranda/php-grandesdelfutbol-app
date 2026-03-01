<?php

use App\Http\Controllers\ClubController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClubInvitationController;
use App\Http\Controllers\ClubJoinController;
use App\Http\Controllers\ClubMemberController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\MatchAttendanceController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\MatchEventController;
use App\Http\Controllers\MatchLifecycleController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerProfileController;
use App\Http\Controllers\PublicMatchController;
use App\Http\Controllers\VenueController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// Public match page (no auth)
Route::get('match/{shareToken}', [PublicMatchController::class, 'show'])->name('match.public');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('clubs', ClubController::class)->except('destroy');

    // Club-scoped routes
    Route::prefix('clubs/{club}')->name('clubs.')->group(function () {
        // Invitations
        Route::get('invite', [ClubInvitationController::class, 'create'])->name('invitations.create');
        Route::post('invite', [ClubInvitationController::class, 'store'])->name('invitations.store');

        // Members
        Route::get('members', [ClubMemberController::class, 'index'])->name('members.index');
        Route::patch('members/{member}/approve', [ClubMemberController::class, 'approve'])->name('members.approve');
        Route::delete('members/{member}/reject', [ClubMemberController::class, 'reject'])->name('members.reject');
        Route::patch('members/{member}/role', [ClubMemberController::class, 'updateRole'])->name('members.updateRole');
        Route::delete('members/{member}', [ClubMemberController::class, 'remove'])->name('members.remove');

        // Players
        Route::resource('players', PlayerController::class)->except('destroy');

        // Venues
        Route::resource('venues', VenueController::class)->except('destroy');
        Route::post('venues/{venue}/fields', [FieldController::class, 'store'])->name('venues.fields.store');
        Route::put('venues/{venue}/fields/{field}', [FieldController::class, 'update'])->name('venues.fields.update');

        // Matches
        Route::resource('matches', MatchController::class)->except('destroy');
        Route::get('matches/{match}/live', [MatchController::class, 'live'])->name('matches.live');
        Route::get('matches/{match}/summary', [MatchController::class, 'summary'])->name('matches.summary');
        Route::post('matches/{match}/attendance', [MatchAttendanceController::class, 'store'])->name('matches.attendance.store');
        Route::patch('matches/{match}/attendance/{attendance}', [MatchAttendanceController::class, 'update'])->name('matches.attendance.update');
        Route::post('matches/{match}/auto-assign', [MatchAttendanceController::class, 'autoAssign'])->name('matches.autoAssign');

        // Match Events
        Route::post('matches/{match}/events', [MatchEventController::class, 'store'])->name('matches.events.store');
        Route::delete('matches/{match}/events/{event}', [MatchEventController::class, 'destroy'])->name('matches.events.destroy');

        // Match Lifecycle
        Route::post('matches/{match}/start', [MatchLifecycleController::class, 'start'])->name('matches.start');
        Route::post('matches/{match}/complete', [MatchLifecycleController::class, 'complete'])->name('matches.complete');
        Route::post('matches/{match}/cancel', [MatchLifecycleController::class, 'cancel'])->name('matches.cancel');
        Route::post('matches/{match}/finalize-stats', [MatchLifecycleController::class, 'finalizeStats'])->name('matches.finalizeStats');
    });

    // Accept invitation via token
    Route::get('clubs/invitations/{token}/accept', [ClubInvitationController::class, 'accept'])->name('invitations.accept');

    // Join via invite link
    Route::get('join/{token}', [ClubJoinController::class, 'show'])->name('clubs.join');
    Route::post('join/{token}', [ClubJoinController::class, 'store'])->name('clubs.join.store');

    // Player profile
    Route::get('player-profile', [PlayerProfileController::class, 'edit'])->name('player-profile.edit');
    Route::patch('player-profile', [PlayerProfileController::class, 'update'])->name('player-profile.update');
});

require __DIR__.'/settings.php';
