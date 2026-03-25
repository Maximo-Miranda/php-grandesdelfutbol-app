<?php

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\MatchStatus;
use App\Enums\PlayerPosition;
use App\Filament\Resources\ClubResource\Pages\ViewClub;
use App\Filament\Resources\ClubResource\RelationManagers\MatchesRelationManager;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Field;
use App\Models\FootballMatch;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use App\Services\PlayerImportService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe('Import Players action', function (): void {
    it('renders the import players action on the ViewClub page', function (): void {
        $user = auth()->user();
        $club = Club::factory()->create(['owner_id' => $user->id]);
        ClubMember::factory()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role' => ClubMemberRole::Owner,
            'status' => ClubMemberStatus::Approved,
        ]);

        Livewire::test(ViewClub::class, ['record' => $club->getRouteKey()])
            ->assertOk()
            ->assertSeeText('Importar Jugadores');
    });
});

describe('PlayerImportService', function (): void {
    it('imports players from a CSV file', function (): void {
        $club = Club::factory()->create();

        $csvContent = "nombre,email,posicion\nJuan Perez,,GK\nMaria Lopez,maria@example.com,ST\nCarlos Ruiz,,\n";
        $filePath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($filePath, $csvContent);

        $service = new PlayerImportService;
        $result = $service->importFromCsv($club, $filePath);

        expect($result['imported'])->toBe(3)
            ->and($result['errors'])->toBeEmpty();

        $juan = Player::query()->where('name', 'Juan Perez')->first();
        expect($juan)->not->toBeNull()
            ->and($juan->position)->toBe(PlayerPosition::Gk)
            ->and($juan->club_id)->toBe($club->id)
            ->and($juan->user_id)->toBeNull()
            ->and($juan->is_active)->toBeTrue();

        $maria = Player::query()->where('name', 'Maria Lopez')->first();
        expect($maria)->not->toBeNull()
            ->and($maria->position)->toBe(PlayerPosition::St);

        $carlos = Player::query()->where('name', 'Carlos Ruiz')->first();
        expect($carlos)->not->toBeNull()
            ->and($carlos->position)->toBeNull();

        @unlink($filePath);
    });

    it('links user when email matches an existing user', function (): void {
        $club = Club::factory()->create();
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $csvContent = "nombre,email\nLinked Player,existing@example.com\n";
        $filePath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($filePath, $csvContent);

        $service = new PlayerImportService;
        $result = $service->importFromCsv($club, $filePath);

        expect($result['imported'])->toBe(1);

        $player = Player::query()->where('name', 'Linked Player')->first();
        expect($player)->not->toBeNull()
            ->and($player->user_id)->toBe($existingUser->id);

        @unlink($filePath);
    });

    it('skips rows with empty names', function (): void {
        $club = Club::factory()->create();

        $csvContent = "nombre,email\n,,\nValid Player,\n";
        $filePath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($filePath, $csvContent);

        $service = new PlayerImportService;
        $result = $service->importFromCsv($club, $filePath);

        expect($result['imported'])->toBe(1);
        expect(Player::query()->where('name', 'Valid Player')->exists())->toBeTrue();

        @unlink($filePath);
    });

    it('returns error when file does not exist', function (): void {
        $club = Club::factory()->create();

        $service = new PlayerImportService;
        $result = $service->importFromCsv($club, '/nonexistent/path.csv');

        expect($result['imported'])->toBe(0)
            ->and($result['errors'])->toHaveCount(1);
    });

    it('returns error when nombre column is missing', function (): void {
        $club = Club::factory()->create();

        $csvContent = "name,email\nJohn,john@test.com\n";
        $filePath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($filePath, $csvContent);

        $service = new PlayerImportService;
        $result = $service->importFromCsv($club, $filePath);

        expect($result['imported'])->toBe(0)
            ->and($result['errors'])->toContain('Columna "nombre" no encontrada en el CSV');

        @unlink($filePath);
    });

    it('resolves position by label', function (): void {
        $club = Club::factory()->create();

        $csvContent = "nombre,posicion\nGoalkeeper Player,portero\nForward Player,delantero\n";
        $filePath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($filePath, $csvContent);

        $service = new PlayerImportService;
        $result = $service->importFromCsv($club, $filePath);

        expect($result['imported'])->toBe(2);

        $gk = Player::query()->where('name', 'Goalkeeper Player')->first();
        expect($gk->position)->toBe(PlayerPosition::Gk);

        $st = Player::query()->where('name', 'Forward Player')->first();
        expect($st->position)->toBe(PlayerPosition::St);

        @unlink($filePath);
    });
});

describe('Create from Template action', function (): void {
    it('creates a match from the most recent match template', function (): void {
        $club = Club::factory()->create();
        $venue = Venue::factory()->create(['club_id' => $club->id]);
        $field = Field::factory()->create(['venue_id' => $venue->id]);

        FootballMatch::factory()->create([
            'club_id' => $club->id,
            'team_a_name' => 'Los Rojos',
            'team_b_name' => 'Los Azules',
            'team_a_color' => '#dc2626',
            'team_b_color' => '#2563eb',
            'max_players' => 7,
            'max_substitutes' => 3,
            'duration_minutes' => 90,
            'arrival_minutes' => 20,
            'registration_opens_hours' => 48,
            'field_id' => $field->id,
            'scheduled_at' => now()->addDays(1),
        ]);

        Livewire::test(MatchesRelationManager::class, [
            'ownerRecord' => $club,
            'pageClass' => ViewClub::class,
        ])
            ->callAction(TestAction::make('create_from_template')->table(), [
                'title' => 'Partido Nuevo',
                'scheduled_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
                'team_a_name' => 'Los Rojos',
                'team_b_name' => 'Los Azules',
                'team_a_color' => '#dc2626',
                'team_b_color' => '#2563eb',
                'max_players' => 7,
                'max_substitutes' => 3,
                'duration_minutes' => 90,
                'arrival_minutes' => 20,
                'registration_opens_hours' => 48,
                'field_id' => $field->id,
            ]);

        $newMatch = FootballMatch::query()
            ->where('club_id', $club->id)
            ->where('title', 'Partido Nuevo')
            ->first();

        expect($newMatch)->not->toBeNull()
            ->and($newMatch->team_a_name)->toBe('Los Rojos')
            ->and($newMatch->team_b_name)->toBe('Los Azules')
            ->and($newMatch->team_a_color)->toBe('#dc2626')
            ->and($newMatch->team_b_color)->toBe('#2563eb')
            ->and($newMatch->max_players)->toBe(7)
            ->and($newMatch->max_substitutes)->toBe(3)
            ->and($newMatch->duration_minutes)->toBe(90)
            ->and($newMatch->arrival_minutes)->toBe(20)
            ->and($newMatch->registration_opens_hours)->toBe(48)
            ->and($newMatch->field_id)->toBe($field->id)
            ->and($newMatch->status)->toBe(MatchStatus::Upcoming);
    });

    it('requires title and scheduled_at', function (): void {
        $club = Club::factory()->create();

        Livewire::test(MatchesRelationManager::class, [
            'ownerRecord' => $club,
            'pageClass' => ViewClub::class,
        ])
            ->callAction(TestAction::make('create_from_template')->table(), [
                'title' => '',
                'scheduled_at' => '',
                'team_a_name' => 'A',
                'team_b_name' => 'B',
                'team_a_color' => '#000000',
                'team_b_color' => '#ffffff',
                'max_players' => 10,
                'max_substitutes' => 4,
                'duration_minutes' => 60,
                'arrival_minutes' => 15,
                'registration_opens_hours' => 24,
            ])
            ->assertHasFormErrors(['title' => 'required', 'scheduled_at' => 'required']);
    });

    it('creates a match even without a previous template match', function (): void {
        $club = Club::factory()->create();

        Livewire::test(MatchesRelationManager::class, [
            'ownerRecord' => $club,
            'pageClass' => ViewClub::class,
        ])
            ->callAction(TestAction::make('create_from_template')->table(), [
                'title' => 'Primer Partido',
                'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'team_a_name' => 'Equipo A',
                'team_b_name' => 'Equipo B',
                'team_a_color' => '#1a1a1a',
                'team_b_color' => '#facc15',
                'max_players' => 10,
                'max_substitutes' => 4,
                'duration_minutes' => 60,
                'arrival_minutes' => 15,
                'registration_opens_hours' => 24,
            ]);

        $match = FootballMatch::query()
            ->where('club_id', $club->id)
            ->where('title', 'Primer Partido')
            ->first();

        expect($match)->not->toBeNull()
            ->and($match->status)->toBe(MatchStatus::Upcoming);
    });
});
