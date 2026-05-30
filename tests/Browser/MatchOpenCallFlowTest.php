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

test('admin crea partido convocatoria general y miembro confirma sin elegir equipo', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create([
        'club_id' => $club->id,
        'user_id' => $admin->id,
    ]);

    $member = User::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $member->id,
    ]);
    Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => $member->id,
    ]);

    // El form arranca con defaults sensatos: sin equipos rosterizados (= open call).
    $this->browse(function (Browser $browser) use ($admin, $club) {
        $browser->loginAs($admin)
            ->visit("/clubs/{$club->ulid}/matches/create")
            ->waitForText('Crear partido')
            ->slowMo();

        // Dismiss el banner PWA que tapa el botón submit en headless.
        $browser->script('document.querySelectorAll(\'.fixed, [class*="bottom-"]\').forEach(el => { if (el.textContent && el.textContent.includes("Instala")) el.remove(); });');

        $browser->scrollIntoView('@match-create-submit')
            ->pause(300)
            ->slowMo()
            ->click('@match-create-submit')
            ->waitForText('Cupos ocupados', 10)
            ->slowMo();
    });

    // El partido se creó como open call.
    $match = FootballMatch::query()->where('club_id', $club->id)->latest()->firstOrFail();
    expect($match->isOpenCall())->toBeTrue()
        ->and($match->team_a_id)->toBeNull()
        ->and($match->team_b_id)->toBeNull();

    // Abrir convocatoria ahora (default es 24h antes del kick-off).
    $match->update(['registration_opens_at' => now()->subHour()]);

    // El miembro confirma sin elegir equipo: el modal no debe aparecer en open call.
    $this->browse(function (Browser $browser) use ($member, $club, $match) {
        $browser->logout()
            ->loginAs($member)
            ->visit("/clubs/{$club->ulid}/matches/{$match->ulid}")
            ->waitForText('Voy', 10)
            ->slowMo();

        $browser->script('document.querySelectorAll(\'.fixed, [class*="bottom-"]\').forEach(el => { if (el.textContent && el.textContent.includes("Instala")) el.remove(); });');

        $browser->scrollIntoView('@match-confirm')
            ->pause(300)
            ->slowMo()
            ->click('@match-confirm')
            ->pause(1500)
            ->slowMo();
    });

    // La attendance quedó en el pool (team=null), sin elegir equipo.
    $memberPlayer = Player::query()->where('user_id', $member->id)->firstOrFail();
    $attendance = $match->attendances()->where('player_id', $memberPlayer->id)->firstOrFail();
    expect($attendance->status)->toBe(AttendanceStatus::Confirmed)
        ->and($attendance->team)->toBeNull();
});
