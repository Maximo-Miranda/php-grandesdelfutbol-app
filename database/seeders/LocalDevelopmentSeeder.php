<?php

namespace Database\Seeders;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\FieldType;
use App\Enums\MatchStatus;
use App\Enums\PlayerPosition;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Field;
use App\Models\FootballMatch;
use App\Models\MatchAttendance;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalDevelopmentSeeder extends Seeder
{
    private const TEAM_COLORS = [
        ['#1a1a1a', '#facc15'],
        ['#dc2626', '#3b82f6'],
        ['#16a34a', '#f97316'],
        ['#7c3aed', '#e5e7eb'],
        ['#0891b2', '#f43f5e'],
    ];

    private const TEAM_NAMES = [
        ['Equipo A', 'Equipo B'],
        ['Oscuros', 'Claros'],
        ['Rojos', 'Azules'],
        ['Negros', 'Blancos'],
        ['Norte', 'Sur'],
    ];

    public function run(): void
    {
        $users = $this->createUsers();
        $clubs = $this->createClubs($users);
        $this->distributeMembers($clubs, $users);
        $fields = $this->createVenuesAndFields($clubs);
        $this->createMatches($clubs, $fields);
        $this->aggregatePlayerStats();
    }

    // ── Users ────────────────────────────────────────────────

    private function createUsers(): Collection
    {
        $mainUser = User::factory()->create([
            'name' => 'Maximo Miranda',
            'email' => 'max@gmail.com',
        ]);

        return User::factory(119)->create()->prepend($mainUser);
    }

    // ── Clubs ────────────────────────────────────────────────

    private function createClubs(Collection $users): Collection
    {
        $mainUser = $users->first();

        $definitions = [
            ['name' => 'Grandes Del Futbol', 'description' => 'Club de amigos apasionados por el futbol.', 'owner' => $mainUser],
            ['name' => 'FC Los Cracks', 'description' => 'Donde nacen las estrellas del barrio.', 'owner' => $users[1]],
            ['name' => 'Deportivo Halcones', 'description' => 'Velocidad y garra en cada partido.', 'owner' => $users[2]],
            ['name' => 'Real Barrio FC', 'description' => 'La pasion del barrio en cada jugada.', 'owner' => $users[3]],
            ['name' => 'Atletico Relámpago', 'description' => 'Rápidos como el relámpago, fuertes como el trueno.', 'owner' => $users[4]],
            ['name' => 'Club Tigres del Norte', 'description' => 'Garra norteña en la cancha.', 'owner' => $users[5]],
        ];

        return collect($definitions)->map(function (array $def) {
            $club = Club::factory()->create([
                'name' => $def['name'],
                'description' => $def['description'],
                'owner_id' => $def['owner']->id,
                'invite_token' => Str::random(32),
                'is_invite_active' => true,
            ]);

            ClubMember::factory()->owner()->create([
                'club_id' => $club->id,
                'user_id' => $def['owner']->id,
            ]);

            Player::factory()->linked($def['owner'])->create([
                'club_id' => $club->id,
                'name' => $def['owner']->name,
                'position' => fake()->randomElement(PlayerPosition::cases()),
                'jersey_number' => 10,
            ]);

            return $club;
        });
    }

    // ── Members ──────────────────────────────────────────────

    private function distributeMembers(Collection $clubs, Collection $users): void
    {
        $mainUser = $users->first();
        $ownerIds = $clubs->pluck('owner_id');
        $available = $users->reject(fn (User $u) => $ownerIds->contains($u->id))->values();

        $memberSlices = [
            [0, 19],   // Grandes Del Futbol: 20 total
            [5, 29],   // FC Los Cracks: 30 total
            [15, 17],  // Deportivo Halcones: 18 total
            [30, 34],  // Real Barrio FC: 35 total
            [60, 14],  // Atletico Relámpago: 15 total
            [75, 21],  // Club Tigres del Norte: 22 total
        ];

        foreach ($clubs as $i => $club) {
            [$offset, $count] = $memberSlices[$i];
            $this->addMembersToClub($club, $available->slice($offset, $count));
        }

        $this->addMembersToClub($clubs[1], collect([$mainUser]));
        $this->addMembersToClub($clubs[3], collect([$mainUser]));

        $this->promoteRandomAdmins($clubs);
    }

    private function addMembersToClub(Club $club, Collection $users): void
    {
        $nextJersey = Player::where('club_id', $club->id)->max('jersey_number') ?? 0;

        foreach ($users as $user) {
            if (ClubMember::where('club_id', $club->id)->where('user_id', $user->id)->exists()) {
                continue;
            }

            ClubMember::factory()->create([
                'club_id' => $club->id,
                'user_id' => $user->id,
                'role' => ClubMemberRole::Player,
                'status' => ClubMemberStatus::Approved,
                'approved_at' => now()->subDays(fake()->numberBetween(1, 365)),
            ]);

            Player::factory()->linked($user)->create([
                'club_id' => $club->id,
                'name' => $user->name,
                'position' => fake()->randomElement(PlayerPosition::cases()),
                'jersey_number' => ++$nextJersey,
            ]);
        }
    }

    private function promoteRandomAdmins(Collection $clubs): void
    {
        foreach ($clubs as $club) {
            ClubMember::where('club_id', $club->id)
                ->where('role', ClubMemberRole::Player)
                ->inRandomOrder()
                ->take(fake()->numberBetween(1, 2))
                ->get()
                ->each(fn (ClubMember $m) => $m->update(['role' => ClubMemberRole::Admin]));
        }
    }

    // ── Venues & Fields ──────────────────────────────────────

    private function createVenuesAndFields(Collection $clubs): Collection
    {
        $venueDefinitions = [
            [0, 'Cancha La Bombonera', 'Av. Principal 123', [['Cancha #1', '5v5'], ['Cancha #2', '7v7']]],
            [0, 'Complejo El Gol', 'Calle Deportiva 456', [['Cancha A', '5v5']]],
            [1, 'Estadio Los Cracks', 'Blvd. Futbol 789', [['Campo Principal', '7v7'], ['Campo Auxiliar', '5v5']]],
            [2, 'Arena Halcones', 'Calle del Deporte 321', [['Cancha Sintetica', '5v5']]],
            [3, 'Complejo La Unión', 'Av. de la Unión 500', [['Cancha Norte', '7v7'], ['Cancha Sur', '5v5']]],
            [3, 'Parque Municipal', 'Calle Independencia 200', [['Campo 1', '7v7']]],
            [4, 'Cancha El Rayo', 'Av. Relámpago 88', [['Cancha Principal', '5v5']]],
            [5, 'Centro Deportivo Tigres', 'Blvd. del Norte 1000', [['Cancha Tigre A', '6v6'], ['Cancha Tigre B', '5v5']]],
        ];

        $fields = collect();

        foreach ($venueDefinitions as [$clubIndex, $venueName, $address, $fieldList]) {
            $venue = Venue::factory()->create([
                'club_id' => $clubs[$clubIndex]->id,
                'name' => $venueName,
                'address' => $address,
            ]);

            foreach ($fieldList as [$fieldName, $fieldType]) {
                $field = Field::factory()->create([
                    'venue_id' => $venue->id,
                    'name' => $fieldName,
                    'field_type' => $fieldType,
                ]);
                $fields->push(['club_id' => $clubs[$clubIndex]->id, 'field' => $field]);
            }
        }

        return $fields;
    }

    // ── Matches ──────────────────────────────────────────────

    private function createMatches(Collection $clubs, Collection $fields): void
    {
        foreach ($clubs as $club) {
            $clubPlayers = Player::where('club_id', $club->id)->get();
            $clubFields = $fields->where('club_id', $club->id)->pluck('field');

            $this->createCompletedMatches($club, $clubPlayers, $clubFields);
            $this->createUpcomingMatches($club, $clubPlayers, $clubFields);
        }
    }

    private function createCompletedMatches(Club $club, Collection $clubPlayers, Collection $clubFields): void
    {
        $totalCount = fake()->numberBetween(45, 55);
        $cancelledIndices = collect(range(0, $totalCount - 1))
            ->random(fake()->numberBetween(2, 5))
            ->all();

        for ($i = 0; $i < $totalCount; $i++) {
            $daysAgo = (int) round(($i / $totalCount) * 350) + fake()->numberBetween(0, 5);
            $scheduledAt = now()->subDays($daysAgo)->setTime(
                fake()->randomElement([9, 10, 18, 19, 20, 21]),
                0,
            );

            $field = $clubFields->random();
            $maxPlayers = $this->maxPlayersFor($field->field_type);
            [$colorSet, $nameSet] = $this->randomTeamConfig();

            if (in_array($i, $cancelledIndices)) {
                FootballMatch::factory()->cancelled()->create([
                    'club_id' => $club->id,
                    'field_id' => $field->id,
                    'title' => $this->matchTitle($field->field_type, $scheduledAt),
                    'scheduled_at' => $scheduledAt,
                    'max_players' => $maxPlayers,
                    ...$this->teamAttributes($nameSet, $colorSet),
                ]);

                continue;
            }

            $match = FootballMatch::factory()->completed()->create([
                'club_id' => $club->id,
                'field_id' => $field->id,
                'title' => $this->matchTitle($field->field_type, $scheduledAt),
                'scheduled_at' => $scheduledAt,
                'max_players' => $maxPlayers,
                'started_at' => $scheduledAt,
                'ended_at' => $scheduledAt->copy()->addMinutes(60),
                'stats_finalized_at' => $scheduledAt->copy()->addHours(2),
                ...$this->teamAttributes($nameSet, $colorSet),
            ]);

            $starters = $this->insertAttendances($match, $clubPlayers, $maxPlayers);
            $this->insertEvents($match, $starters);
        }
    }

    private function createUpcomingMatches(Club $club, Collection $clubPlayers, Collection $clubFields): void
    {
        $registrationHours = 24;

        // 1-2 matches with registration open (within next 24h) — players already confirmed
        $openCount = fake()->numberBetween(1, 2);
        for ($i = 0; $i < $openCount; $i++) {
            $scheduledAt = now()
                ->addHours(fake()->numberBetween(2, $registrationHours))
                ->setMinute(0)->setSecond(0);

            $match = $this->createUpcomingMatch($club, $clubFields, $scheduledAt, $registrationHours);
            $this->insertUpcomingAttendances($match, $clubPlayers, $match->max_players);
        }

        // 2-3 matches further out (registration not yet open) — no attendances
        $futureCount = fake()->numberBetween(2, 3);
        for ($i = 0; $i < $futureCount; $i++) {
            $scheduledAt = now()
                ->addDays(fake()->numberBetween(3, 21))
                ->setTime(fake()->randomElement([18, 19, 20, 21]), 0);

            $this->createUpcomingMatch($club, $clubFields, $scheduledAt, $registrationHours);
        }
    }

    private function createUpcomingMatch(Club $club, Collection $clubFields, $scheduledAt, int $registrationHours): FootballMatch
    {
        $field = $clubFields->random();
        $maxPlayers = $this->maxPlayersFor($field->field_type);
        [$colorSet, $nameSet] = $this->randomTeamConfig();

        return FootballMatch::factory()->create([
            'club_id' => $club->id,
            'field_id' => $field->id,
            'title' => $this->matchTitle($field->field_type, $scheduledAt),
            'scheduled_at' => $scheduledAt,
            'max_players' => $maxPlayers,
            'registration_opens_hours' => $registrationHours,
            ...$this->teamAttributes($nameSet, $colorSet),
        ]);
    }

    // ── Attendances ──────────────────────────────────────────

    /** @return int[] */
    private function insertAttendances(FootballMatch $match, Collection $clubPlayers, int $maxPlayers): array
    {
        $attendeeCount = min($clubPlayers->count(), fake()->numberBetween($maxPlayers, $maxPlayers + 4));
        $attending = $clubPlayers->shuffle()->take($attendeeCount);
        $declined = $clubPlayers->diff($attending)->shuffle()->take(
            fake()->numberBetween(1, max(1, intdiv($clubPlayers->count(), 6))),
        );

        $half = intdiv($maxPlayers, 2);
        $starters = [];
        $rows = [];
        $now = now();
        $index = 0;

        foreach ($attending as $player) {
            $isStarter = $index < $maxPlayers;

            if ($isStarter) {
                $starters[] = $player->id;
            }

            $rows[] = [
                'match_id' => $match->id,
                'player_id' => $player->id,
                'status' => 'confirmed',
                'role' => $isStarter ? 'starter' : 'substitute',
                'team' => $isStarter ? ($index < $half ? 'a' : 'b') : null,
                'confirmed_at' => $match->scheduled_at->copy()->subHours(fake()->numberBetween(1, 48)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $index++;
        }

        foreach ($declined as $player) {
            $rows[] = [
                'match_id' => $match->id,
                'player_id' => $player->id,
                'status' => 'declined',
                'role' => 'pending',
                'team' => null,
                'confirmed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('match_attendances')->insert($rows);

        return $starters;
    }

    private function insertUpcomingAttendances(FootballMatch $match, Collection $clubPlayers, int $maxPlayers): void
    {
        $respondents = $clubPlayers->shuffle()->take(
            min($clubPlayers->count(), fake()->numberBetween(4, $maxPlayers + 4)),
        );

        $starterCount = 0;
        $half = intdiv($maxPlayers, 2);
        $rows = [];
        $now = now();

        foreach ($respondents as $player) {
            $confirmed = fake()->boolean(85);
            $role = 'pending';
            $team = null;

            if ($confirmed) {
                $role = $starterCount < $maxPlayers ? 'starter' : 'substitute';
                if ($starterCount < $maxPlayers) {
                    $team = $starterCount < $half ? 'a' : 'b';
                }
                $starterCount++;
            }

            $rows[] = [
                'match_id' => $match->id,
                'player_id' => $player->id,
                'status' => $confirmed ? 'confirmed' : 'declined',
                'role' => $role,
                'team' => $team,
                'confirmed_at' => $confirmed ? $now->copy()->subHours(fake()->numberBetween(1, 72)) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('match_attendances')->insert($rows);
    }

    // ── Events ───────────────────────────────────────────────

    /** @param int[] $starters */
    private function insertEvents(FootballMatch $match, array $starters): void
    {
        if (empty($starters)) {
            return;
        }

        $rows = [];
        $now = now();

        $goalCount = fake()->numberBetween(3, 8);
        for ($i = 0; $i < $goalCount; $i++) {
            $scorer = fake()->randomElement($starters);
            $minute = fake()->numberBetween(1, 60);

            $rows[] = $this->eventRow($match->id, $scorer, 'goal', $minute, $now);

            if (fake()->boolean(60) && count($starters) > 1) {
                $assister = fake()->randomElement(array_filter($starters, fn ($id) => $id !== $scorer));
                $rows[] = $this->eventRow($match->id, $assister, 'assist', $minute, $now);
            }
        }

        $yellowCount = fake()->numberBetween(0, 3);
        for ($i = 0; $i < $yellowCount; $i++) {
            $rows[] = $this->eventRow($match->id, fake()->randomElement($starters), 'yellow_card', fake()->numberBetween(1, 60), $now);
        }

        if (fake()->boolean(15)) {
            $rows[] = $this->eventRow($match->id, fake()->randomElement($starters), 'red_card', fake()->numberBetween(30, 60), $now);
        }

        DB::table('match_events')->insert($rows);
    }

    private function eventRow(int $matchId, int $playerId, string $type, int $minute, $now): array
    {
        return [
            'match_id' => $matchId,
            'player_id' => $playerId,
            'event_type' => $type,
            'minute' => $minute,
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    // ── Stats Aggregation ────────────────────────────────────

    private function aggregatePlayerStats(): void
    {
        Player::query()->update([
            'goals' => 0,
            'assists' => 0,
            'matches_played' => 0,
            'yellow_cards' => 0,
            'red_cards' => 0,
        ]);

        $finalizedMatchIds = FootballMatch::where('status', MatchStatus::Completed)
            ->whereNotNull('stats_finalized_at')
            ->pluck('id');

        MatchAttendance::query()
            ->whereIn('match_id', $finalizedMatchIds)
            ->where('status', 'confirmed')
            ->selectRaw('player_id, count(*) as total')
            ->groupBy('player_id')
            ->pluck('total', 'player_id')
            ->each(fn ($count, $playerId) => Player::where('id', $playerId)->update(['matches_played' => $count]));

        DB::table('match_events')
            ->whereIn('match_id', $finalizedMatchIds)
            ->selectRaw('player_id, event_type, count(*) as total')
            ->groupBy('player_id', 'event_type')
            ->get()
            ->each(function ($event) {
                $field = match ($event->event_type) {
                    'goal', 'penalty_scored', 'own_goal' => 'goals',
                    'assist' => 'assists',
                    'yellow_card' => 'yellow_cards',
                    'red_card' => 'red_cards',
                    default => null,
                };

                if ($field) {
                    Player::where('id', $event->player_id)->increment($field, $event->total);
                }
            });
    }

    // ── Helpers ───────────────────────────────────────────────

    private function matchTitle(FieldType $fieldType, $date): string
    {
        return "Partido {$fieldType->value} {$date->locale('es')->isoFormat('ddd D MMM')}";
    }

    private function maxPlayersFor(FieldType $fieldType): int
    {
        return match ($fieldType) {
            FieldType::FiveVsFive => 10,
            FieldType::SixVsSix => 12,
            FieldType::SevenVsSeven => 14,
            FieldType::EightVsEight => 16,
            FieldType::NineVsNine => 18,
            FieldType::TenVsTen => 20,
            FieldType::ElevenVsEleven => 22,
        };
    }

    /** @return array{array{string, string}, array{string, string}} */
    private function randomTeamConfig(): array
    {
        return [
            self::TEAM_COLORS[array_rand(self::TEAM_COLORS)],
            self::TEAM_NAMES[array_rand(self::TEAM_NAMES)],
        ];
    }

    /** @return array{team_a_name: string, team_b_name: string, team_a_color: string, team_b_color: string} */
    private function teamAttributes(array $names, array $colors): array
    {
        return [
            'team_a_name' => $names[0],
            'team_b_name' => $names[1],
            'team_a_color' => $colors[0],
            'team_b_color' => $colors[1],
        ];
    }
}
