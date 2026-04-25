<?php

use App\Models\Club;
use App\Models\Player;
use App\Models\PlayerProfile;
use App\Models\User;

/**
 * @param  array<string, mixed>  $playerAttributes
 */
function makePublicPlayer(bool $clubIsPublic = true, ?bool $profileIsPublic = true, array $playerAttributes = []): Player
{
    $club = Club::factory()->create(['is_public' => $clubIsPublic]);
    $userId = null;

    if ($profileIsPublic !== null) {
        $user = User::factory()->create();
        PlayerProfile::factory()->create(['user_id' => $user->id, 'is_public_profile' => $profileIsPublic]);
        $userId = $user->id;
    }

    return Player::factory()->create([
        'club_id' => $club->id,
        'user_id' => $userId,
        ...$playerAttributes,
    ]);
}

test('guests can view a public player score card', function () {
    $player = makePublicPlayer(playerAttributes: ['goals' => 12, 'assists' => 5]);

    $this->get(route('player.public', $player->ulid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('players/Public')
            ->where('player.stats.goals', 12)
            ->where('player.stats.assists', 5)
            ->where('club.ulid', $player->club->ulid)
        );
});

test('player public page 404s when profile is private', function () {
    $player = makePublicPlayer(profileIsPublic: false);

    $this->get(route('player.public', $player->ulid))->assertNotFound();
});

test('player public page 404s when club is not public', function () {
    $player = makePublicPlayer(clubIsPublic: false);

    $this->get(route('player.public', $player->ulid))->assertNotFound();
});

test('player without a user account is treated as public', function () {
    $player = makePublicPlayer(profileIsPublic: null);

    $this->get(route('player.public', $player->ulid))->assertOk();
});
