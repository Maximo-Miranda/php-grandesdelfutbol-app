<?php

use App\Enums\AttendanceStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;

uses(DatabaseTruncation::class);

test('el jugador declina la asistencia con el botón No voy y queda como declined', function () {
    // Convocatoria general (sin equipos) para aislar la acción de declinar.
    $club = Club::factory()->create();

    $member = User::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $member->id,
    ]);
    $memberPlayer = Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => $member->id,
        'name' => 'Juan Declina',
    ]);

    // display_name = nickname del perfil ?? name. Asertamos el valor real que
    // resuelve el modelo (cubre ambos caminos) en el listado de "No van".
    $displayName = $memberPlayer->display_name;

    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'team_a_id' => null,
        'team_b_id' => null,
        'registration_opens_at' => now()->subHour(),
    ]);

    $this->browse(function (Browser $browser) use ($member, $club, $match, $displayName) {
        $browser->loginAs($member)
            ->visit("/clubs/{$club->ulid}/matches/{$match->ulid}")
            ->waitForText('No voy', 10)
            ->slowMo();

        $browser->script('document.querySelectorAll(\'.fixed, [class*="bottom-"]\').forEach(el => { if (el.textContent && el.textContent.includes("Instala")) el.remove(); });');

        $browser->scrollIntoView('@match-decline')
            ->pause(300)
            ->slowMo()
            ->click('@match-decline')
            ->pause(1500)
            ->slowMo();

        // La sección "No van" aparece tras declinar y lista al jugador por su nombre.
        $browser->waitForText('No van', 5)
            ->slowMo()
            ->assertSee('No van')
            ->assertSee($displayName);
    });

    // La attendance quedó como declined (sin equipo).
    $attendance = $match->attendances()->where('player_id', $memberPlayer->id)->firstOrFail();
    expect($attendance->status)->toBe(AttendanceStatus::Declined)
        ->and($attendance->team)->toBeNull();
});
