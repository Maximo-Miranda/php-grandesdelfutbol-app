<?php

use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\Player;

test('public match page is accessible without authentication', function () {
    $match = FootballMatch::factory()->create(['share_token' => 'test-share-token']);

    $this->get(route('match.public', 'test-share-token'))
        ->assertOk();
});

test('public match page shows match details with events', function () {
    $match = FootballMatch::factory()->completed()->create(['share_token' => 'share123']);
    $player = Player::factory()->create(['club_id' => $match->club_id]);
    MatchEvent::factory()->goal()->create(['match_id' => $match->id, 'player_id' => $player->id]);

    $this->get(route('match.public', 'share123'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('matches/Public')
            ->has('match')
            ->where('match.share_token', 'share123')
            ->has('match.events', 1)
        );
});

test('public match page returns 404 for invalid token', function () {
    $this->get(route('match.public', 'invalid-token'))
        ->assertNotFound();
});
