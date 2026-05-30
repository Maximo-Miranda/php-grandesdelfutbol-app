<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceTeam;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;

uses(DatabaseTruncation::class);

/*
 * Partido rosterizado estricto (Bayer vs Manchester, allow_outsiders=false):
 * el rosterizado entra a su nómina sin modal; el outsider es rechazado por el
 * backend y no se crea attendance.
 */

test('partido con dos equipos rosterizados: el rosterizado va a su nómina sin modal y el outsider es rechazado', function () {
    $club = Club::factory()->create();
    $season = Season::factory()->create(['club_id' => $club->id]);

    $bayer = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'name' => 'Bayer',
    ]);
    $manchester = Team::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'name' => 'Manchester',
    ]);

    $rosterUser = User::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $rosterUser->id,
    ]);
    $rosterPlayer = Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => $rosterUser->id,
    ]);
    $bayer->players()->attach($rosterPlayer->id);

    // Con ambas nóminas vacías, resolveTeamForPlayer auto-popula team A para
    // outsiders. Este jugador en Manchester garantiza que el outsider sí se rechace.
    $mcPlayer = Player::factory()->create(['club_id' => $club->id]);
    $manchester->players()->attach($mcPlayer->id);

    $outsiderUser = User::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $outsiderUser->id,
    ]);
    $outsiderPlayer = Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => $outsiderUser->id,
    ]);

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $bayer->id,
        'team_b_id' => $manchester->id,
        'allow_outsiders' => false,
        'registration_opens_at' => now()->subHour(),
    ]);

    // El rosterizado de Bayer confirma: va directo a team A sin ver el modal.
    $this->browse(function (Browser $browser) use ($rosterUser, $club, $match) {
        $browser->loginAs($rosterUser)
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
            ->assertDontSee('Elige tu equipo');
    });

    $rosterAttendance = $match->attendances()->where('player_id', $rosterPlayer->id)->firstOrFail();
    expect($rosterAttendance->status)->toBe(AttendanceStatus::Confirmed)
        ->and($rosterAttendance->team)->toBe(AttendanceTeam::A);

    // El outsider sí ve el modal (el frontend lo cree elegible para ambos equipos),
    // pero al elegir Bayer el backend lo rechaza: defensa en profundidad.
    $this->browse(function (Browser $browser) use ($outsiderUser, $club, $match) {
        $browser->logout()
            ->loginAs($outsiderUser)
            ->visit("/clubs/{$club->ulid}/matches/{$match->ulid}")
            ->waitForText('Voy', 10)
            ->slowMo();

        $browser->script('document.querySelectorAll(\'.fixed, [class*="bottom-"]\').forEach(el => { if (el.textContent && el.textContent.includes("Instala")) el.remove(); });');

        $browser->scrollIntoView('@match-confirm')
            ->pause(300)
            ->slowMo()
            ->click('@match-confirm')
            ->waitForText('Elige tu equipo', 5)
            ->slowMo();

        $browser->press('Bayer')
            ->pause(2000)
            ->slowMo()
            ->assertSee('no está en la nómina de este partido');
    });

    expect(MatchAttendance::query()
        ->where('match_id', $match->id)
        ->where('player_id', $outsiderPlayer->id)
        ->exists())->toBeFalse();
});
