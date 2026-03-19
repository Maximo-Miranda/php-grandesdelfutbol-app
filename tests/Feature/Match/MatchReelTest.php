<?php

use App\Enums\MatchEventType;
use App\Enums\ReelStatus;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\MatchReel;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

test('generate endpoint dispatches correct number of jobs', function () {
    Bus::fake();

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'youtube_url' => 'https://youtube.com/watch?v=test123',
    ]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->assist()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.generate', [$club, $match]))
        ->assertRedirect();

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 2;
    });

    expect(MatchReel::count())->toBe(2);
});

test('generate fails without youtube url', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'youtube_url' => null,
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.generate', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('generate fails when reels already exist', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'youtube_url' => 'https://youtube.com/watch?v=test123',
    ]);

    MatchReel::factory()->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.generate', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('admin can delete a reel', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $reel = MatchReel::factory()->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->delete(route('clubs.matches.reels.destroy', [$club, $match, $reel]))
        ->assertRedirect();

    $this->assertDatabaseMissing('match_reels', ['id' => $reel->id]);
});

test('reel model has correct relationships', function () {
    $match = FootballMatch::factory()->create();
    $player = Player::factory()->create(['club_id' => $match->club_id]);
    $event = MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    $reel = MatchReel::factory()->forEvent($event)->create();

    expect($reel->match)->toBeInstanceOf(FootballMatch::class)
        ->and($reel->event)->toBeInstanceOf(MatchEvent::class)
        ->and($reel->player)->toBeInstanceOf(Player::class);
});

test('reel casts status to ReelStatus enum', function () {
    $reel = MatchReel::factory()->create();

    expect($reel->status)->toBeInstanceOf(ReelStatus::class)
        ->and($reel->status)->toBe(ReelStatus::Pending);
});

test('team and neutral events are excluded from reel generation', function () {
    Bus::fake();

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        'youtube_url' => 'https://youtube.com/watch?v=test123',
    ]);
    $player = Player::factory()->create(['club_id' => $club->id]);

    // Player event - should generate reel
    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    // Team event - should NOT generate reel
    MatchEvent::factory()->teamEvent(MatchEventType::ShotOnTarget, 'a')->create(['match_id' => $match->id]);

    // Neutral event - should NOT generate reel
    MatchEvent::factory()->neutralEvent(MatchEventType::Timeout)->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.generate', [$club, $match]))
        ->assertRedirect();

    expect(MatchReel::count())->toBe(1);
});
