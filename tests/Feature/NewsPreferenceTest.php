<?php

use App\Ai\Agents\ExtractNewsPreferences;
use App\Jobs\ExtractUserNewsPreferences;
use App\Models\User;
use App\Models\UserNewsPreference;
use Illuminate\Support\Facades\Queue;

test('guests cannot access preferences', function () {
    $this->get(route('news.preferences.create'))
        ->assertRedirect(route('login'));
});

test('preferences page loads for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('news.preferences.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('news/Preferences')
            ->has('availableCompetitions')
        );
});

test('user can save competition preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('news.preferences.store'), [
            'competitions' => ['la_liga', 'champions_league'],
        ])
        ->assertRedirect(route('news.feed'));

    $this->assertDatabaseHas('user_news_preferences', [
        'user_id' => $user->id,
        'onboarding_completed' => true,
    ]);

    $preference = $user->fresh()->newsPreference;
    expect($preference->competitions)->toBe(['la_liga', 'champions_league']);
});

test('free text triggers synchronous AI extraction on save', function () {
    ExtractNewsPreferences::fake([
        [
            'teams' => ['real_madrid'],
            'competitions' => [],
            'topics' => ['transfers'],
        ],
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('news.preferences.store'), [
            'competitions' => ['premier_league'],
            'free_text_input' => 'Me interesa el Real Madrid y los fichajes del Barcelona',
        ])
        ->assertRedirect(route('news.feed'));

    $preference = $user->fresh()->newsPreference;

    expect($preference->ai_extracted_entities)->not->toBeNull();
    expect($preference->ai_extracted_entities['teams'])->toContain('real_madrid');
    expect($preference->ai_extracted_entities['topics'])->toContain('transfers');
});

test('empty free text does not dispatch AI job', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('news.preferences.store'), [
            'competitions' => ['la_liga'],
        ])
        ->assertRedirect(route('news.feed'));

    Queue::assertNotPushed(ExtractUserNewsPreferences::class);
});

test('update preferences overwrites existing', function () {
    $user = User::factory()->create();
    UserNewsPreference::factory()->create([
        'user_id' => $user->id,
        'competitions' => ['la_liga'],
        'teams' => ['real_madrid'],
    ]);

    $this->actingAs($user)
        ->patch(route('news.preferences.update'), [
            'competitions' => ['premier_league', 'champions_league'],
            'teams' => ['liverpool'],
        ])
        ->assertRedirect(route('news.feed'));

    $preference = $user->fresh()->newsPreference;
    expect($preference->competitions)->toBe(['premier_league', 'champions_league']);
    expect($preference->teams)->toBe(['liverpool']);
});

test('free text is limited to 500 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('news.preferences.store'), [
            'free_text_input' => str_repeat('a', 501),
        ])
        ->assertSessionHasErrors('free_text_input');
});

test('AI extraction job writes only to ai_extracted_entities without touching user pill selections', function () {
    ExtractNewsPreferences::fake([
        [
            'teams' => ['junior_de_barranquilla'],
            'competitions' => ['liga_betplay'],
            'topics' => [],
        ],
    ]);

    $user = User::factory()->create();
    $preference = UserNewsPreference::factory()->create([
        'user_id' => $user->id,
        'teams' => null,
        'competitions' => ['la_liga'],
        'topics' => null,
        'free_text_input' => 'Me interesa Junior nada mas',
    ]);

    (new ExtractUserNewsPreferences($user, 'Me interesa Junior nada mas'))->handle();

    $preference->refresh();

    // User's explicit pill selections stay as-is — AI does not override them.
    expect($preference->competitions)->toBe(['la_liga']);
    expect($preference->teams)->toBeNull();
    expect($preference->topics)->toBeNull();

    // AI-derived entities live in their own column.
    expect($preference->ai_extracted_entities)->not->toBeNull();
    expect($preference->ai_extracted_entities['teams'])->toContain('junior_de_barranquilla');
    expect($preference->ai_extracted_entities['competitions'])->toContain('liga_betplay');

    ExtractNewsPreferences::assertPrompted(fn ($prompt) => $prompt->contains('Junior'));
});

test('deselecting a competition persists an empty array even with free text', function () {
    ExtractNewsPreferences::fake([
        [
            'teams' => [],
            'competitions' => ['mundial'],
            'topics' => [],
        ],
    ]);

    $user = User::factory()->create();
    UserNewsPreference::factory()->create([
        'user_id' => $user->id,
        'competitions' => ['mundial'],
        'free_text_input' => 'me interesa mundial',
    ]);

    // User unchecks the "Mundial" pill and saves, keeping the free-text.
    $this->actingAs($user)
        ->post(route('news.preferences.store'), [
            'competitions' => [],
            'free_text_input' => 'me interesa mundial',
        ])
        ->assertRedirect(route('news.feed'));

    $preference = $user->fresh()->newsPreference;

    expect($preference->competitions)->toBe([]);
    expect($preference->ai_extracted_entities['competitions'])->toContain('mundial');
});

test('clearing free text wipes ai_extracted_entities but preserves the pill columns', function () {
    Queue::fake();

    $user = User::factory()->create();
    UserNewsPreference::factory()->create([
        'user_id' => $user->id,
        'competitions' => ['la_liga'],
        'teams' => ['real_madrid'],
        'topics' => ['transfers'],
        'free_text_input' => 'Me interesa Junior y los fichajes',
        'ai_extracted_entities' => ['teams' => ['junior_de_barranquilla'], 'competitions' => [], 'topics' => []],
    ]);

    $this->actingAs($user)
        ->post(route('news.preferences.store'), [
            'competitions' => ['champions_league'],
            'free_text_input' => null,
        ])
        ->assertRedirect(route('news.feed'));

    $preference = $user->fresh()->newsPreference;

    expect($preference->competitions)->toBe(['champions_league']);
    expect($preference->teams)->toBe(['real_madrid']);
    expect($preference->topics)->toBe(['transfers']);
    expect($preference->ai_extracted_entities)->toBeNull();
    expect($preference->free_text_input)->toBeNull();

    Queue::assertNotPushed(ExtractUserNewsPreferences::class);
});
