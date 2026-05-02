<?php

use App\Enums\AttendanceStatus;
use App\Enums\ClubMemberRole;
use App\Enums\PlayerPosition;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\User;
use App\Notifications\WaitlistDemotedByGoalkeeperNotification;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Support\Facades\Notification;

test('members can register players for a match', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
        'role' => 'starter',
    ]);
});

test('non-members cannot register players', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertForbidden();
});

test('player can register with team choice', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
            'team' => 'a',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
        'team' => 'a',
        'role' => 'starter',
    ]);
});

test('player can register without team choice', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
        'team' => null,
    ]);
});

test('player is placed on waitlist when team is full', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2, // 1 per team
        'max_substitutes' => 2, // 1 sub per team
    ]);

    // Fill team A: 1 starter + 1 sub = full
    for ($i = 0; $i < 2; $i++) {
        $player = Player::factory()->create(['club_id' => $club->id]);
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => 'confirmed',
            'team' => 'a',
            'role' => $i === 0 ? 'starter' : 'substitute',
        ]);
    }

    $newPlayer = Player::factory()->create(['club_id' => $club->id, 'position' => PlayerPosition::Cm]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $newPlayer->id,
            'status' => 'confirmed',
            'team' => 'a',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Quedaste en lista de espera.');

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $newPlayer->id,
        'status' => 'waitlisted',
        'role' => 'pending',
        'team' => 'a',
    ]);
});

test('can confirm player on team with room even when other team is full', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 4, // 2 per team
        'max_substitutes' => 2, // 1 sub per team
    ]);

    // Fill team A completely (2 starters + 1 sub)
    for ($i = 0; $i < 3; $i++) {
        $player = Player::factory()->create(['club_id' => $club->id]);
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => 'confirmed',
            'team' => 'a',
            'role' => $i < 2 ? 'starter' : 'substitute',
        ]);
    }

    // Team B should still accept players
    $newPlayer = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $newPlayer->id,
            'status' => 'confirmed',
            'team' => 'b',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $newPlayer->id,
        'role' => 'starter',
        'team' => 'b',
    ]);
});

test('can still decline player when match is full', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2,
        'max_substitutes' => 0,
    ]);

    for ($i = 0; $i < 2; $i++) {
        $player = Player::factory()->create(['club_id' => $club->id]);
        MatchAttendance::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'status' => 'confirmed',
        ]);
    }

    $newPlayer = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $newPlayer->id,
            'status' => 'declined',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $newPlayer->id,
        'status' => 'declined',
    ]);
});

test('waitlist is ordered FIFO by confirmed_at', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2,
        'max_substitutes' => 0,
    ]);

    // Fill starters on team A
    $p1 = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $p1->id,
    ]);

    // 2 players join and should go to waitlist in order
    $first = Player::factory()->create(['club_id' => $club->id]);
    $second = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $first->id,
            'status' => 'confirmed',
            'team' => 'a',
        ]);

    $this->travel(1)->seconds();

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $second->id,
            'status' => 'confirmed',
            'team' => 'a',
        ]);

    $waitlisted = MatchAttendance::where('match_id', $match->id)
        ->where('status', AttendanceStatus::Waitlisted)
        ->orderBy('confirmed_at')
        ->get();

    expect($waitlisted)->toHaveCount(2)
        ->and($waitlisted[0]->player_id)->toBe($first->id)
        ->and($waitlisted[1]->player_id)->toBe($second->id);
});

test('first waitlisted player is promoted when a confirmed player declines', function () {
    Notification::fake();

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2,
        'max_substitutes' => 0,
    ]);

    $starter = Player::factory()->create(['club_id' => $club->id]);
    $starterAttendance = MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $starter->id,
    ]);

    // Fill team B too so the match is truly full
    $teamBStarter = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $teamBStarter->id,
    ]);

    // Waiting player joins waitlist
    $waiting = Player::factory()->create(['club_id' => $club->id, 'user_id' => User::factory()->create()->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $waiting->id,
        'status' => AttendanceStatus::Waitlisted,
        'team' => 'a',
        'confirmed_at' => now(),
    ]);

    // Starter declines
    $this->actingAs($user)
        ->patch(route('clubs.matches.attendance.update', [$club, $match, $starterAttendance]), [
            'status' => 'declined',
        ])
        ->assertRedirect();

    // Waiting player should now be confirmed as starter
    $promoted = MatchAttendance::where('player_id', $waiting->id)->first();
    expect($promoted->status)->toBe(AttendanceStatus::Confirmed);

    Notification::assertSentTo($waiting->user, WaitlistPromotedNotification::class);
});

test('goalkeeper cascade displaces last substitute to waitlist when match is full', function () {
    Notification::fake();

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2,  // 1 per team
        'max_substitutes' => 2, // 1 sub per team
    ]);

    // Fill team A: 1 outfield starter + 1 outfield substitute (no GK)
    $teamAStarter = Player::factory()->create([
        'club_id' => $club->id,
        'position' => PlayerPosition::Cm,
    ]);
    $teamASub = Player::factory()->create([
        'club_id' => $club->id,
        'position' => PlayerPosition::St,
        'user_id' => User::factory()->create()->id,
    ]);
    MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $teamAStarter->id,
        'confirmed_at' => now()->subMinutes(10),
    ]);
    MatchAttendance::factory()->teamA()->create([
        'match_id' => $match->id,
        'player_id' => $teamASub->id,
        'role' => 'substitute',
        'confirmed_at' => now()->subMinutes(5),
    ]);

    // Fill team B too so match is totally full
    $teamBStarter = Player::factory()->create(['club_id' => $club->id]);
    $teamBSub = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $teamBStarter->id,
    ]);
    MatchAttendance::factory()->teamB()->create([
        'match_id' => $match->id,
        'player_id' => $teamBSub->id,
        'role' => 'substitute',
    ]);

    // GK joins team A (which has no GK starter)
    $gk = Player::factory()->create([
        'club_id' => $club->id,
        'position' => PlayerPosition::Gk,
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $gk->id,
            'status' => 'confirmed',
            'team' => 'a',
        ])
        ->assertRedirect();

    // GK is starter
    expect(MatchAttendance::where('player_id', $gk->id)->first()->role->value)->toBe('starter');

    // Old starter demoted to substitute (or kept as starter if GK ranked higher)
    // Old substitute pushed to waitlist
    expect(MatchAttendance::where('player_id', $teamASub->id)->first()->status->value)->toBe('waitlisted');

    // Displaced player gets notified
    Notification::assertSentTo($teamASub->user, WaitlistDemotedByGoalkeeperNotification::class);
});

test('waitlist is cleared when match is cancelled', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2,
        'max_substitutes' => 0,
    ]);

    $waiting = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $waiting->id,
        'status' => AttendanceStatus::Waitlisted,
        'role' => 'pending',
        'confirmed_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.cancel', [$club, $match]))
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $waiting->id,
        'status' => 'declined',
    ]);
});

test('waitlist is not promoted when match is cancelled', function () {
    Notification::fake();

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2,
        'max_substitutes' => 0,
        'status' => 'cancelled',
    ]);

    $starter = Player::factory()->create(['club_id' => $club->id]);
    $starterAtt = MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $starter->id,
    ]);

    $waiting = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->create([
        'match_id' => $match->id,
        'player_id' => $waiting->id,
        'status' => AttendanceStatus::Waitlisted,
        'team' => 'a',
        'confirmed_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete(route('clubs.matches.attendance.destroy', [$club, $match, $starterAtt]))
        ->assertRedirect();

    $stillWaiting = MatchAttendance::where('player_id', $waiting->id)->first();
    expect($stillWaiting->status)->toBe(AttendanceStatus::Waitlisted);

    Notification::assertNothingSent();
});

test('waitlisted player is promoted to the other team when their preferred team is still full', function () {
    Notification::fake();

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2,  // 1 starter per team
        'max_substitutes' => 2, // 1 sub per team
    ]);

    // Team A: full (1 starter + 1 sub)
    $aStarter = Player::factory()->create(['club_id' => $club->id]);
    $aSub = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $aStarter->id,
    ]);
    MatchAttendance::factory()->teamA()->create([
        'match_id' => $match->id,
        'player_id' => $aSub->id,
        'role' => 'substitute',
    ]);

    // Team B: full (1 starter + 1 sub)
    $bStarter = Player::factory()->create(['club_id' => $club->id]);
    $bSub = Player::factory()->create(['club_id' => $club->id]);
    $bStarterAttendance = MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $bStarter->id,
    ]);
    MatchAttendance::factory()->teamB()->create([
        'match_id' => $match->id,
        'player_id' => $bSub->id,
        'role' => 'substitute',
    ]);

    // Waitlisted player prefers team A
    $waiting = Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => User::factory()->create()->id,
    ]);
    MatchAttendance::factory()->teamA()->create([
        'match_id' => $match->id,
        'player_id' => $waiting->id,
        'status' => AttendanceStatus::Waitlisted,
        'role' => 'pending',
        'confirmed_at' => now(),
    ]);

    // Admin removes team B starter — team A is still full
    $this->actingAs($user)
        ->delete(route('clubs.matches.attendance.destroy', [$club, $match, $bStarterAttendance]))
        ->assertRedirect();

    // Waitlisted player should be promoted to team B (the team with the opening)
    $promoted = MatchAttendance::where('player_id', $waiting->id)->first();
    expect($promoted->status)->toBe(AttendanceStatus::Confirmed)
        ->and($promoted->team->value)->toBe('b');

    Notification::assertSentTo($waiting->user, WaitlistPromotedNotification::class);
});

test('goalkeeper goes to waitlist when full match already has GK starter on same team', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'max_players' => 2,
        'max_substitutes' => 2,
    ]);

    // Team A already has GK starter + outfield sub
    $gk1 = Player::factory()->create(['club_id' => $club->id, 'position' => PlayerPosition::Gk]);
    $outfieldSub = Player::factory()->create(['club_id' => $club->id, 'position' => PlayerPosition::St]);
    MatchAttendance::factory()->teamA()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $gk1->id,
    ]);
    MatchAttendance::factory()->teamA()->create([
        'match_id' => $match->id,
        'player_id' => $outfieldSub->id,
        'role' => 'substitute',
    ]);

    // Fill team B
    $bStarter = Player::factory()->create(['club_id' => $club->id]);
    $bSub = Player::factory()->create(['club_id' => $club->id]);
    MatchAttendance::factory()->teamB()->starter()->create([
        'match_id' => $match->id,
        'player_id' => $bStarter->id,
    ]);
    MatchAttendance::factory()->teamB()->create([
        'match_id' => $match->id,
        'player_id' => $bSub->id,
        'role' => 'substitute',
    ]);

    // Another GK confirms for team A — no cascade, just waitlist
    $gk2 = Player::factory()->create(['club_id' => $club->id, 'position' => PlayerPosition::Gk]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $gk2->id,
            'status' => 'confirmed',
            'team' => 'a',
        ])
        ->assertRedirect();

    $att = MatchAttendance::where('player_id', $gk2->id)->first();
    expect($att->status)->toBe(AttendanceStatus::Waitlisted);

    // Nobody got demoted
    expect(MatchAttendance::where('player_id', $outfieldSub->id)->first()->role->value)->toBe('substitute');
});

test('members cannot register before registration window opens', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->addDays(5),
        'registration_opens_hours' => 24,
    ]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertInvalid(['status']);

    $this->assertDatabaseMissing('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
    ]);
});

test('admins can register before registration window opens', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create([
        'club_id' => $club->id,
        'user_id' => $admin->id,
        'role' => ClubMemberRole::Admin,
    ]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->addDays(5),
        'registration_opens_hours' => 24,
    ]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($admin)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
    ]);
});

test('members can register once registration window opens', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->create([
        'club_id' => $club->id,
        'scheduled_at' => now()->addHours(10),
        'registration_opens_hours' => 24,
    ]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.attendance.store', [$club, $match]), [
            'player_id' => $player->id,
            'status' => 'confirmed',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('match_attendances', [
        'match_id' => $match->id,
        'player_id' => $player->id,
        'status' => 'confirmed',
    ]);
});
