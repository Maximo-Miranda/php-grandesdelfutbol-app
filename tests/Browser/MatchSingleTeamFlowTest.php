<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;

uses(DatabaseTruncation::class);

test('partido un equipo vs externos: rosterizado confirma auto al team sin modal', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id]);
    $teamA = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'name' => 'Bayer',
    ]);

    $member = User::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $member->id,
    ]);
    $memberPlayer = Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => $member->id,
    ]);
    $teamA->players()->attach($memberPlayer->id);

    // Partido single-team: solo team A seleccionado, sin team B
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $teamA->id,
        'team_b_id' => null,
        'registration_opens_at' => now()->subHour(),
    ]);

    // El miembro confirma sin elegir equipo: va automático a team A.
    $this->browse(function (Browser $browser) use ($member, $club, $match) {
        $browser->loginAs($member)
            ->visit("/clubs/{$club->ulid}/matches/{$match->ulid}")
            ->waitForText('Voy', 10)
            ->slowMo();

        $browser->script('document.querySelectorAll(\'.fixed, [class*="bottom-"]\').forEach(el => { if (el.textContent && el.textContent.includes("Instala")) el.remove(); });');

        $browser->scrollIntoView('@match-confirm')
            ->pause(300)
            ->slowMo()
            ->click('@match-confirm')
            ->pause(1500)
            ->slowMo()
            // El modal de elegir equipo NO debe aparecer en single-team
            ->assertDontSee('Elige tu equipo');
    });

    // La attendance quedó en team A (auto-asignado por la nómina).
    $attendance = $match->attendances()->where('player_id', $memberPlayer->id)->firstOrFail();
    expect($attendance->status)->toBe(AttendanceStatus::Confirmed)
        ->and($attendance->team)->toBe(AttendanceTeam::A);
});
