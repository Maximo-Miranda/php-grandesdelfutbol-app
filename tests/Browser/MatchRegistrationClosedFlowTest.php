<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;

uses(DatabaseTruncation::class);

test('con la convocatoria cerrada el boton de confirmar no aparece en la UI', function () {
    $club = Club::factory()->create();

    $member = User::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $member->id,
    ]);
    Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => $member->id,
    ]);

    // Partido futuro (sigue upcoming) pero con la ventana de convocatoria ya cerrada:
    // abrió hace 3h y cerró hace 1h; el kickoff es en 2h.
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'team_a_id' => null,
        'team_b_id' => null,
        'scheduled_at' => now()->addHours(2),
        'registration_opens_at' => now()->subHours(3),
        'registration_closes_at' => now()->subHour(),
    ]);

    $this->browse(function (Browser $browser) use ($member, $club, $match) {
        $browser->loginAs($member)
            ->visit("/clubs/{$club->ulid}/matches/{$match->ulid}")
            ->waitForText('La confirmacion de asistencia', 10)
            ->slowMo()
            ->assertMissing('@match-confirm')
            ->assertMissing('@match-decline');
    });
});
