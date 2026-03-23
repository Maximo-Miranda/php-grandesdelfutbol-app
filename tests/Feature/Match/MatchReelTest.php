<?php

use App\Enums\MatchEventType;
use App\Enums\ReelSource;
use App\Enums\ReelStatus;
use App\Jobs\GenerateMatchReel;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\MatchReel;
use App\Models\MatchVideoUpload;
use App\Models\Player;
use App\Models\User;
use App\Services\ReelService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

function createMatchWithVideo(array $matchOverrides = [], array $videoOverrides = []): array
{
    $club = Club::factory()->create();
    $admin = User::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $admin->id]);
    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
        ...$matchOverrides,
    ]);
    $videoUpload = MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $admin->id,
        ...$videoOverrides,
    ]);

    return [$club, $match, $admin, $videoUpload];
}

test('generate endpoint dispatches jobs for goals and highlighted events only', function () {
    Bus::fake();

    [$club, $match, $user] = createMatchWithVideo();
    $player = Player::factory()->create(['club_id' => $club->id]);

    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->assist()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'event_type' => MatchEventType::Save,
        'highlighted' => true,
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.generate', [$club, $match]))
        ->assertRedirect();

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 2;
    });

    expect(MatchReel::count())->toBe(2);
});

test('generate fails without video upload', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.generate', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('generate fails when video is not ready', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    MatchVideoUpload::factory()->encoding()->create([
        'football_match_id' => $match->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.generate', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('generate endpoint allows regeneration and preserves manual reels', function () {
    Bus::fake();

    [$club, $match, $user] = createMatchWithVideo();

    $orphanReel = MatchReel::factory()->create(['match_id' => $match->id, 'source' => 'auto', 'status' => 'completed']);
    $manualReel = MatchReel::factory()->create(['match_id' => $match->id, 'source' => 'manual']);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.generate', [$club, $match]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($orphanReel->fresh())->toBeNull();
    expect($manualReel->fresh())->not->toBeNull();
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

test('only goals, penalty scored, and highlighted events generate reels', function () {
    Bus::fake();

    [$club, $match, $user] = createMatchWithVideo();
    $player = Player::factory()->create(['club_id' => $club->id]);

    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->assist()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    MatchEvent::factory()->teamEvent(MatchEventType::ShotOnTarget, 'a')->create(['match_id' => $match->id]);
    MatchEvent::factory()->neutralEvent(MatchEventType::Timeout)->create(['match_id' => $match->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.generate', [$club, $match]))
        ->assertRedirect();

    expect(MatchReel::count())->toBe(1);
});

test('admin can create manual clip with minute and second', function () {
    Queue::fake();

    [$club, $match, $user] = createMatchWithVideo();

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.store', [$club, $match]), [
            'title' => 'Jugada increíble',
            'minute' => 2,
            'second' => 0,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $reel = MatchReel::first();
    expect($reel->title)->toBe('Jugada increíble')
        ->and($reel->source)->toBe(ReelSource::Manual)
        ->and($reel->status)->toBe(ReelStatus::Pending)
        ->and($reel->start_second)->toBe(105)
        ->and($reel->end_second)->toBe(130)
        ->and($reel->duration)->toBe(25);

    Queue::assertPushed(GenerateMatchReel::class);
});

test('manual clip requires ready video', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create([
        'club_id' => $club->id,
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.store', [$club, $match]), [
            'title' => 'Test',
            'minute' => 1,
            'second' => 0,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('any member can create match-level reel with minute and second', function () {
    Queue::fake();

    [$club, $match, $admin] = createMatchWithVideo();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.request', [$club, $match]), [
            'minute' => 5,
            'second' => 30,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $reel = MatchReel::first();
    expect($reel->source)->toBe(ReelSource::Request)
        ->and($reel->status)->toBe(ReelStatus::Pending)
        ->and($reel->player_id)->toBeNull()
        ->and($reel->requested_by)->toBeNull()
        ->and($reel->start_second)->toBe(315)
        ->and($reel->end_second)->toBe(340)
        ->and($reel->duration)->toBe(25);

    Queue::assertPushed(GenerateMatchReel::class);
});

test('player can create player-level reel with minute and second', function () {
    Queue::fake();

    [$club, $match, $admin] = createMatchWithVideo();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $player = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.requestForPlayer', [$club, $match]), [
            'minute' => 1,
            'second' => 0,
            'request_notes' => 'Mi gol del minuto 1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $reel = MatchReel::first();
    expect($reel->source)->toBe(ReelSource::Request)
        ->and($reel->status)->toBe(ReelStatus::Pending)
        ->and($reel->requested_by)->toBe($user->id)
        ->and($reel->player_id)->toBe($player->id)
        ->and($reel->request_notes)->toBe('Mi gol del minuto 1')
        ->and($reel->start_second)->toBe(45)
        ->and($reel->end_second)->toBe(70);

    Queue::assertPushed(GenerateMatchReel::class);
});

test('admin can approve clip request', function () {
    Queue::fake();

    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $reel = MatchReel::factory()->requested()->create([
        'match_id' => $match->id,
    ]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.approve', [$club, $match, $reel]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $reel->refresh();
    expect($reel->status)->toBe(ReelStatus::Pending);

    Queue::assertPushed(GenerateMatchReel::class);
});

test('admin can reject clip request', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $reel = MatchReel::factory()->requested()->create([
        'match_id' => $match->id,
    ]);

    $this->actingAs($user)
        ->delete(route('clubs.matches.reels.reject', [$club, $match, $reel]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('match_reels', ['id' => $reel->id]);
});

test('cannot approve non-requested reel', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $reel = MatchReel::factory()->create(['match_id' => $match->id, 'status' => ReelStatus::Pending]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.approve', [$club, $match, $reel]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('admin can toggle event highlighted', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);
    $player = Player::factory()->create(['club_id' => $club->id]);
    $event = MatchEvent::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'highlighted' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('clubs.matches.events.update', [$club, $match, $event]), [
            'highlighted' => true,
        ])
        ->assertRedirect();

    $event->refresh();
    expect($event->highlighted)->toBeTrue();
});

test('reel source casts to ReelSource enum', function () {
    $reel = MatchReel::factory()->manual()->create();

    expect($reel->source)->toBeInstanceOf(ReelSource::class)
        ->and($reel->source)->toBe(ReelSource::Manual);
});

test('regeneration skips reels with unchanged times', function () {
    Bus::fake();

    [$club, $match, $user, $videoUpload] = createMatchWithVideo([], ['video_offset_seconds' => 0]);
    $player = Player::factory()->create(['club_id' => $club->id]);
    $event = MatchEvent::factory()->goal()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'minute' => 10,
        'second' => 30,
    ]);

    $unchangedReel = MatchReel::factory()->create([
        'match_id' => $match->id,
        'event_id' => $event->id,
        'source' => ReelSource::Auto,
        'status' => ReelStatus::Completed,
        'start_second' => 615,
        'end_second' => 640,
    ]);

    $manualReel = MatchReel::factory()->manual()->create(['match_id' => $match->id]);

    app(ReelService::class)->generateReelsForMatch($match);

    expect($unchangedReel->fresh())->not->toBeNull()
        ->and($manualReel->fresh())->not->toBeNull()
        ->and(MatchReel::where('source', 'auto')->count())->toBe(1);

    Bus::assertNothingBatched();
});

test('regeneration recreates reels with changed times', function () {
    Bus::fake();

    [$club, $match, $user, $videoUpload] = createMatchWithVideo([], ['video_offset_seconds' => 0]);
    $player = Player::factory()->create(['club_id' => $club->id]);
    $event = MatchEvent::factory()->goal()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'minute' => 10,
        'second' => 30,
    ]);

    $staleReel = MatchReel::factory()->create([
        'match_id' => $match->id,
        'event_id' => $event->id,
        'source' => ReelSource::Auto,
        'status' => ReelStatus::Completed,
        'start_second' => 100,
        'end_second' => 125,
    ]);

    app(ReelService::class)->generateReelsForMatch($match);

    expect($staleReel->fresh())->toBeNull()
        ->and(MatchReel::where('source', 'auto')->count())->toBe(1);

    $newReel = MatchReel::where('source', 'auto')->first();
    expect($newReel->start_second)->toBe(615)
        ->and($newReel->end_second)->toBe(640);
});

test('regeneration removes orphaned auto reels for un-highlighted events', function () {
    Bus::fake();

    [$club, $match, $user] = createMatchWithVideo();
    $player = Player::factory()->create(['club_id' => $club->id]);

    $event = MatchEvent::factory()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'event_type' => MatchEventType::YellowCard,
        'highlighted' => false,
    ]);

    $orphanReel = MatchReel::factory()->create([
        'match_id' => $match->id,
        'event_id' => $event->id,
        'source' => ReelSource::Auto,
    ]);

    app(ReelService::class)->generateReelsForMatch($match);

    expect($orphanReel->fresh())->toBeNull();
});

test('regeneration retries failed reels', function () {
    Bus::fake();

    [$club, $match, $user, $videoUpload] = createMatchWithVideo([], ['video_offset_seconds' => 0]);
    $player = Player::factory()->create(['club_id' => $club->id]);
    $event = MatchEvent::factory()->goal()->create([
        'match_id' => $match->id,
        'player_id' => $player->id,
        'minute' => 10,
        'second' => 30,
    ]);

    $failedReel = MatchReel::factory()->create([
        'match_id' => $match->id,
        'event_id' => $event->id,
        'source' => ReelSource::Auto,
        'status' => ReelStatus::Failed,
        'start_second' => 615,
        'end_second' => 640,
    ]);

    app(ReelService::class)->generateReelsForMatch($match);

    expect($failedReel->fresh())->toBeNull()
        ->and(MatchReel::where('source', 'auto')->where('status', ReelStatus::Pending)->count())->toBe(1);
});

test('clip request validates second max 59', function () {
    [$club, $match, $admin] = createMatchWithVideo();
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.request', [$club, $match]), [
            'minute' => 5,
            'second' => 70,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('second');
});

test('manual clip validates second max 59', function () {
    [$club, $match, $user] = createMatchWithVideo();

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.store', [$club, $match]), [
            'title' => 'Test',
            'minute' => 1,
            'second' => 70,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('second');
});

test('manual clip rejects minute exceeding video duration', function () {
    [$club, $match, $user] = createMatchWithVideo([], ['duration_seconds' => 4440]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.store', [$club, $match]), [
            'minute' => 80,
            'second' => 0,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('minute');
});

test('manual clip allows minute within video duration', function () {
    Queue::fake();

    [$club, $match, $user] = createMatchWithVideo([], ['duration_seconds' => 4440]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.store', [$club, $match]), [
            'minute' => 70,
            'second' => 0,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('player reel request rejects minute exceeding video duration', function () {
    [$club, $match, $admin] = createMatchWithVideo([], ['duration_seconds' => 3600]);
    $user = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.requestForPlayer', [$club, $match]), [
            'minute' => 65,
            'second' => 0,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('minute');
});

test('time validation skips when video duration is null', function () {
    Queue::fake();

    [$club, $match, $user] = createMatchWithVideo([], ['duration_seconds' => null]);

    $this->actingAs($user)
        ->post(route('clubs.matches.reels.store', [$club, $match]), [
            'minute' => 65,
            'second' => 0,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('summary page includes myPlayer prop', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $player = Player::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);
    $match = FootballMatch::factory()->completed()->create(['club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('clubs.matches.summary', [$club, $match]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/matches/Summary')
            ->has('myPlayer')
            ->where('myPlayer.id', $player->id)
        );
});
