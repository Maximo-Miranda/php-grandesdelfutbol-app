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

test('partido con nominas + outsiders: outsider entra al pool y admin sortea', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create([
        'club_id' => $club->id,
        'user_id' => $admin->id,
    ]);

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

    // Un jugador rosterizado en cada equipo
    $bayerPlayer = Player::factory()->create(['club_id' => $club->id]);
    $manchesterPlayer = Player::factory()->create(['club_id' => $club->id]);
    $bayer->players()->attach($bayerPlayer->id);
    $manchester->players()->attach($manchesterPlayer->id);

    // Outsider — miembro del club sin nómina
    $outsiderUser = User::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $outsiderUser->id,
    ]);
    $outsiderPlayer = Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => $outsiderUser->id,
    ]);

    // Partido rosterizado CON outsiders permitidos
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'season_id' => $season->id,
        'team_a_id' => $bayer->id,
        'team_b_id' => $manchester->id,
        'allow_outsiders' => true,
        'registration_opens_at' => now()->subHour(),
    ]);

    // Confirmar rosterizados directo en BD (no es el foco del test)
    MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $bayerPlayer->id,
    ]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $manchesterPlayer->id,
    ]);

    // Outsider confirma: entra al pool (team=null, sin modal).
    $this->browse(function (Browser $browser) use ($outsiderUser, $club, $match) {
        $browser->loginAs($outsiderUser)
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

    $outsiderAttendance = $match->attendances()->where('player_id', $outsiderPlayer->id)->firstOrFail();
    expect($outsiderAttendance->status)->toBe(AttendanceStatus::Confirmed)
        ->and($outsiderAttendance->team)->toBeNull();

    // Admin entra y sortea: el outsider queda asignado a un team.
    $this->browse(function (Browser $browser) use ($admin, $club, $match) {
        $browser->logout()
            ->loginAs($admin)
            ->visit("/clubs/{$club->ulid}/matches/{$match->ulid}")
            ->waitForText('Sortear equipos', 10)
            ->slowMo();

        $browser->script('document.querySelectorAll(\'.fixed, [class*="bottom-"]\').forEach(el => { if (el.textContent && el.textContent.includes("Instala")) el.remove(); });');

        $browser->scrollIntoView('@match-auto-assign')
            ->pause(300)
            ->slowMo()
            ->click('@match-auto-assign')
            ->pause(1500)
            ->slowMo();
    });

    // El outsider quedó asignado a algún team (A o B).
    $outsiderAttendance = $match->attendances()->where('player_id', $outsiderPlayer->id)->firstOrFail();
    expect($outsiderAttendance->team)->not->toBeNull();

    // Rosterizados deben mantener su nómina
    expect($match->attendances()->where('player_id', $bayerPlayer->id)->first()->team)->toBe(AttendanceTeam::A)
        ->and($match->attendances()->where('player_id', $manchesterPlayer->id)->first()->team)->toBe(AttendanceTeam::B);
});
