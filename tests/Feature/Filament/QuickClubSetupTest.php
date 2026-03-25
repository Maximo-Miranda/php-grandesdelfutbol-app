<?php

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\InvitationStatus;
use App\Enums\MatchStatus;
use App\Filament\Pages\QuickClubSetup;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\User;
use App\Notifications\ClubInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('can render the page', function (): void {
    Livewire::test(QuickClubSetup::class)
        ->assertOk();
});

it('can create a club with only required fields', function (): void {
    $user = auth()->user();

    Livewire::test(QuickClubSetup::class)
        ->fillForm([
            'name' => 'Mi Club de Futbol',
            'team_a_name' => 'Equipo A',
            'team_b_name' => 'Equipo B',
            'team_a_color' => '#10b981',
            'team_b_color' => '#3b82f6',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $club = Club::query()->where('name', 'Mi Club de Futbol')->first();

    expect($club)->not->toBeNull()
        ->and($club->owner_id)->toBe($user->id)
        ->and($club->slug)->toBe('mi-club-de-futbol')
        ->and($club->invite_token)->not->toBeNull()
        ->and($club->requires_approval)->toBeTrue()
        ->and($club->is_invite_active)->toBeTrue();

    $member = ClubMember::query()
        ->where('club_id', $club->id)
        ->where('user_id', $user->id)
        ->first();

    expect($member)->not->toBeNull()
        ->and($member->role)->toBe(ClubMemberRole::Owner)
        ->and($member->status)->toBe(ClubMemberStatus::Approved);

    $player = Player::query()
        ->where('club_id', $club->id)
        ->where('user_id', $user->id)
        ->first();

    expect($player)->not->toBeNull()
        ->and($player->name)->toBe($user->name)
        ->and($player->is_active)->toBeTrue();
});

it('can create a club with invitations', function (): void {
    Notification::fake();

    Livewire::test(QuickClubSetup::class)
        ->fillForm([
            'name' => 'Club con Invitaciones',
            'team_a_name' => 'Equipo A',
            'team_b_name' => 'Equipo B',
            'team_a_color' => '#10b981',
            'team_b_color' => '#3b82f6',
            'emails' => "player1@example.com\nplayer2@example.com",
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $club = Club::query()->where('name', 'Club con Invitaciones')->first();

    expect($club)->not->toBeNull();

    $invitations = ClubInvitation::query()->where('club_id', $club->id)->get();

    expect($invitations)->toHaveCount(2)
        ->and($invitations->pluck('email')->sort()->values()->all())
        ->toBe(['player1@example.com', 'player2@example.com']);

    expect($invitations->first()->status)->toBe(InvitationStatus::Pending);

    Notification::assertSentTimes(ClubInvitationNotification::class, 2);
});

it('can create a club with a first match', function (): void {
    Livewire::test(QuickClubSetup::class)
        ->fillForm([
            'name' => 'Club con Partido',
            'team_a_name' => 'Los Azules',
            'team_b_name' => 'Los Rojos',
            'team_a_color' => '#3b82f6',
            'team_b_color' => '#dc2626',
            'match_title' => 'Partido Inaugural',
            'scheduled_date' => '2026-04-15',
            'scheduled_time' => '20:00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $club = Club::query()->where('name', 'Club con Partido')->first();

    expect($club)->not->toBeNull();

    $match = FootballMatch::query()->where('club_id', $club->id)->first();

    expect($match)->not->toBeNull()
        ->and($match->title)->toBe('Partido Inaugural')
        ->and($match->status)->toBe(MatchStatus::Upcoming)
        ->and($match->team_a_name)->toBe('Los Azules')
        ->and($match->team_b_name)->toBe('Los Rojos')
        ->and($match->team_a_color)->toBe('#3b82f6')
        ->and($match->team_b_color)->toBe('#dc2626');
});

it('requires the club name', function (): void {
    Livewire::test(QuickClubSetup::class)
        ->fillForm([
            'name' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('skips invitations when no emails provided', function (): void {
    Livewire::test(QuickClubSetup::class)
        ->fillForm([
            'name' => 'Club Sin Invitaciones',
            'team_a_name' => 'Equipo A',
            'team_b_name' => 'Equipo B',
            'team_a_color' => '#10b981',
            'team_b_color' => '#3b82f6',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $club = Club::query()->where('name', 'Club Sin Invitaciones')->first();

    expect(ClubInvitation::query()->where('club_id', $club->id)->count())->toBe(0);
});

it('skips match creation when no match info provided', function (): void {
    Livewire::test(QuickClubSetup::class)
        ->fillForm([
            'name' => 'Club Sin Partido',
            'team_a_name' => 'Equipo A',
            'team_b_name' => 'Equipo B',
            'team_a_color' => '#10b981',
            'team_b_color' => '#3b82f6',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $club = Club::query()->where('name', 'Club Sin Partido')->first();

    expect(FootballMatch::query()->where('club_id', $club->id)->count())->toBe(0);
});

it('filters invalid emails from invitation list', function (): void {
    Notification::fake();

    Livewire::test(QuickClubSetup::class)
        ->fillForm([
            'name' => 'Club Filtro Email',
            'team_a_name' => 'Equipo A',
            'team_b_name' => 'Equipo B',
            'team_a_color' => '#10b981',
            'team_b_color' => '#3b82f6',
            'emails' => "valid@example.com\nnot-an-email\n\nanother-valid@example.com",
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $club = Club::query()->where('name', 'Club Filtro Email')->first();

    $invitations = ClubInvitation::query()->where('club_id', $club->id)->get();

    expect($invitations)->toHaveCount(2)
        ->and($invitations->pluck('email')->sort()->values()->all())
        ->toBe(['another-valid@example.com', 'valid@example.com']);
});
