<?php

use App\Jobs\DeleteClub;
use App\Models\Attachment;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubMember;
use App\Models\Field;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use App\Services\AttachmentService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('guests cannot delete a club', function () {
    $club = Club::factory()->create();

    $this->delete(route('clubs.destroy', $club))
        ->assertRedirect(route('login'));
});

test('regular members cannot delete a club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('clubs.destroy', $club))
        ->assertForbidden();
});

test('admins cannot delete a club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('clubs.destroy', $club))
        ->assertForbidden();
});

test('owner can delete a club and job is dispatched', function () {
    Queue::fake();

    $user = User::factory()->create();
    $club = Club::factory()->create(['owner_id' => $user->id]);
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $user->update(['last_club_id' => $club->id]);

    $this->actingAs($user)
        ->delete(route('clubs.destroy', $club))
        ->assertRedirect(route('clubs.index'));

    Queue::assertPushed(DeleteClub::class, function (DeleteClub $job) use ($club) {
        return $job->clubId === $club->id;
    });

    $user->refresh();
    expect($user->last_club_id)->toBeNull();
    expect(ClubMember::where('club_id', $club->id)->where('user_id', $user->id)->exists())->toBeFalse();
});

test('job deletes all club data', function () {
    Storage::fake('public');

    $club = Club::factory()->create();
    $owner = User::factory()->create();
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $owner->id]);

    $invitation = ClubInvitation::factory()->create(['club_id' => $club->id]);

    $player = Player::factory()->create(['club_id' => $club->id]);

    $venue = Venue::factory()->create(['club_id' => $club->id]);
    $field = Field::factory()->create(['venue_id' => $venue->id]);

    $match = FootballMatch::factory()->create(['club_id' => $club->id, 'field_id' => $field->id]);
    $attendance = MatchAttendance::factory()->create(['match_id' => $match->id, 'player_id' => $player->id]);
    $event = MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    $attachment = Attachment::factory()->create([
        'attachable_type' => Club::class,
        'attachable_id' => $club->id,
    ]);
    Storage::disk('public')->put($attachment->path, 'fake-content');

    (new DeleteClub($club->id))->handle(app(AttachmentService::class));

    expect(Club::find($club->id))->toBeNull()
        ->and(ClubMember::where('club_id', $club->id)->exists())->toBeFalse()
        ->and(ClubInvitation::where('club_id', $club->id)->exists())->toBeFalse()
        ->and(Player::where('club_id', $club->id)->exists())->toBeFalse()
        ->and(Venue::where('club_id', $club->id)->exists())->toBeFalse()
        ->and(Field::find($field->id))->toBeNull()
        ->and(FootballMatch::where('club_id', $club->id)->exists())->toBeFalse()
        ->and(MatchAttendance::find($attendance->id))->toBeNull()
        ->and(MatchEvent::find($event->id))->toBeNull()
        ->and(Attachment::find($attachment->id))->toBeNull();

    Storage::disk('public')->assertMissing($attachment->path);
});

test('job is idempotent when club is already deleted', function () {
    $job = new DeleteClub(999999);

    $job->handle(app(AttachmentService::class));

    expect(true)->toBeTrue();
});

test('deleting active club clears session', function () {
    Queue::fake();

    $user = User::factory()->create();
    $club = Club::factory()->create(['owner_id' => $user->id]);
    ClubMember::factory()->owner()->create(['club_id' => $club->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->withSession(['active_club_id' => $club->id])
        ->delete(route('clubs.destroy', $club))
        ->assertRedirect(route('clubs.index'))
        ->assertSessionMissing('active_club_id');
});
