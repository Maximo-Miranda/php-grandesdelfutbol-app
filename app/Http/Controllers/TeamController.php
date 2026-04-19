<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollection;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Models\Club;
use App\Models\Player;
use App\Models\Team;
use App\Services\AttachmentService;
use App\Services\SeasonService;
use App\Services\StandingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(
        private readonly SeasonService $seasons,
        private readonly AttachmentService $attachments,
        private readonly StandingsService $standings,
    ) {}

    public function index(Club $club): Response
    {
        Gate::authorize('viewAny', [Team::class, $club]);

        $teams = $club->teams()
            ->with(['season', 'attachments', 'coach', 'captain', 'players'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn (Team $t) => $t->season->ulid);

        return Inertia::render('clubs/teams/Index', [
            'club' => $club,
            'isAdmin' => $club->isAdminOrOwner(request()->user()),
            'teamsBySeason' => $teams->map(function ($teams, $seasonUlid) {
                $season = $teams->first()->season;

                return [
                    'season' => [
                        'ulid' => $seasonUlid,
                        'name' => $season->name,
                        'is_active' => $season->isActive(),
                    ],
                    'teams' => $teams->map(fn (Team $t) => $this->presentTeam($t))->values(),
                ];
            })->values(),
            'hasPreviousSeasonWithTeams' => $this->hasPreviousSeasonWithTeams($club),
        ]);
    }

    public function create(Club $club): Response
    {
        Gate::authorize('create', [Team::class, $club]);

        $activeSeason = $this->seasons->activeFor($club);

        return Inertia::render('clubs/teams/Create', [
            'club' => $club,
            'activeSeason' => [
                'ulid' => $activeSeason->ulid,
                'name' => $activeSeason->name,
            ],
            'players' => $club->players()->active()->orderBy('name')->get(['id', 'ulid', 'name', 'jersey_number', 'position']),
        ]);
    }

    public function store(StoreTeamRequest $request, Club $club): RedirectResponse
    {
        $data = $request->validated();

        $season = isset($data['season_id'])
            ? $club->seasons()->findOrFail($data['season_id'])
            : $this->seasons->activeFor($club);

        $team = DB::transaction(function () use ($club, $season, $data, $request) {
            $team = $club->teams()->create([
                'season_id' => $season->id,
                'name' => $data['name'],
                'color' => $data['color'],
                'coach_player_id' => $data['coach_player_id'] ?? null,
                'captain_player_id' => $data['captain_player_id'] ?? null,
                'bio' => $data['bio'] ?? null,
                'is_tournament' => (bool) ($data['is_tournament'] ?? false),
            ]);

            if ($request->hasFile('logo')) {
                $this->attachments->upload($team, $request->file('logo'), AttachmentCollection::TeamLogo);
            }
            if ($request->hasFile('cover')) {
                $this->attachments->upload($team, $request->file('cover'), AttachmentCollection::TeamCover);
            }

            return $team;
        });

        if ($request->boolean('_redirect_back') || $request->wantsJson()) {
            return redirect()->back()->with('success', 'Equipo creado.')->with('team', $this->presentTeam($team));
        }

        return redirect()->route('clubs.teams.show', [$club, $team])->with('success', 'Equipo creado.');
    }

    public function show(Club $club, Team $team): Response
    {
        Gate::authorize('view', $team);

        $team->load(['season', 'attachments', 'coach', 'captain', 'players']);

        $standings = $this->standings->forSeason($team->season);
        $row = $standings->firstWhere('team_id', $team->id) ?? [
            'PJ' => 0, 'G' => 0, 'E' => 0, 'P' => 0, 'GF' => 0, 'GC' => 0, 'DG' => 0, 'Pts' => 0, 'last5' => [],
        ];

        $recentMatches = $team->matchesAsA()->withoutGlobalScopes()
            ->orWhere('team_b_id', $team->id)
            ->where('season_id', $team->season_id)
            ->orderByDesc('scheduled_at')
            ->limit(10)
            ->get(['id', 'ulid', 'title', 'scheduled_at', 'team_a_id', 'team_b_id', 'team_a_name', 'team_b_name', 'team_a_score', 'team_b_score', 'status', 'is_friendly']);

        return Inertia::render('clubs/teams/Show', [
            'club' => $club,
            'team' => $this->presentTeam($team, full: true),
            'stats' => $row,
            'recentMatches' => $recentMatches,
            'canEdit' => Gate::allows('update', $team),
        ]);
    }

    public function edit(Club $club, Team $team): Response
    {
        Gate::authorize('update', $team);

        $team->load(['season', 'attachments', 'coach', 'captain', 'players']);

        return Inertia::render('clubs/teams/Edit', [
            'club' => $club,
            'team' => $this->presentTeam($team, full: true),
            'players' => $team->is_tournament
                ? $club->players()->active()->orderBy('name')->get(['id', 'ulid', 'name', 'jersey_number', 'position'])
                : $this->availablePlayersFor($club, $team->season_id, $team->id),
        ]);
    }

    public function update(UpdateTeamRequest $request, Club $club, Team $team): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($team, $data, $request) {
            $team->update(Arr::only($data, ['name', 'color', 'coach_player_id', 'captain_player_id', 'bio', 'is_tournament']));

            if (array_key_exists('player_ids', $data)) {
                $playerIds = $data['player_ids'] ?? [];
                $team->detachPlayersFromSiblings($playerIds);
                $team->players()->sync($playerIds);
            }

            $this->syncAttachment($team, $request, 'logo', AttachmentCollection::TeamLogo, ! empty($data['remove_logo']));
            $this->syncAttachment($team, $request, 'cover', AttachmentCollection::TeamCover, ! empty($data['remove_cover']));
        });

        return redirect()->route('clubs.teams.show', [$club, $team])->with('success', 'Equipo actualizado.');
    }

    private function syncAttachment(Team $team, Request $request, string $fileKey, AttachmentCollection $collection, bool $remove): void
    {
        if ($remove) {
            $existing = $team->getAttachment($collection);
            if ($existing) {
                $this->attachments->delete($existing);
            }
        }

        if ($request->hasFile($fileKey)) {
            $this->attachments->upload($team, $request->file($fileKey), $collection);
        }
    }

    public function destroy(Club $club, Team $team): RedirectResponse
    {
        Gate::authorize('delete', $team);

        $inUse = $team->matchesAsA()->withoutGlobalScopes()->exists()
            || $team->matchesAsB()->withoutGlobalScopes()->exists();

        if ($inUse) {
            return redirect()->back()->withErrors(['team' => 'No puedes eliminar un equipo que tiene partidos asociados.']);
        }

        $team->delete();

        return redirect()->route('clubs.teams.index', $club)->with('success', 'Equipo eliminado.');
    }

    public function copyFromPrevious(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('create', [Team::class, $club]);

        $active = $this->seasons->activeFor($club);
        $previous = $club->seasons()
            ->where('id', '!=', $active->id)
            ->orderByDesc('created_at')
            ->first();

        if (! $previous) {
            return redirect()->back()->withErrors(['copy' => 'No hay temporada anterior de la cual copiar.']);
        }

        DB::transaction(function () use ($active, $previous) {
            foreach ($previous->teams()->with(['players', 'attachments'])->get() as $sourceTeam) {
                $alreadyExists = $active->teams()
                    ->where('normalized_name', $sourceTeam->normalized_name)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                $newTeam = $active->teams()->create([
                    'club_id' => $sourceTeam->club_id,
                    'name' => $sourceTeam->name,
                    'color' => $sourceTeam->color,
                    'coach_player_id' => $sourceTeam->coach_player_id,
                    'captain_player_id' => $sourceTeam->captain_player_id,
                    'bio' => $sourceTeam->bio,
                ]);

                $sourcePlayerIds = $sourceTeam->players->pluck('id')->all();
                $newTeam->detachPlayersFromSiblings($sourcePlayerIds);
                $newTeam->players()->sync($sourcePlayerIds);

                foreach ($sourceTeam->attachments as $att) {
                    $newTeam->attachments()->create([
                        'collection' => $att->collection,
                        'disk' => $att->disk,
                        'path' => $att->path,
                        'original_name' => $att->original_name,
                        'mime_type' => $att->mime_type,
                        'size' => $att->size,
                    ]);
                }
            }
        });

        return redirect()->route('clubs.teams.index', $club)->with('success', 'Equipos copiados de la temporada anterior.');
    }

    private function hasPreviousSeasonWithTeams(Club $club): bool
    {
        $active = $club->seasons()->active()->first();
        $query = $club->seasons()->has('teams');
        if ($active) {
            $query->where('id', '!=', $active->id);
        }

        return $query->exists();
    }

    /**
     * Players of the club not yet in any non-tournament team of the given season.
     * Used when editing a non-tournament team to enforce exclusive membership.
     * The current team's roster is included so those players render as pre-selected.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function availablePlayersFor(Club $club, int $seasonId, ?int $excludingTeamId = null): Collection
    {
        $takenIds = DB::table('team_player')
            ->join('teams', 'teams.id', '=', 'team_player.team_id')
            ->where('teams.season_id', $seasonId)
            ->where('teams.is_tournament', false)
            ->when($excludingTeamId, fn ($q) => $q->where('teams.id', '!=', $excludingTeamId))
            ->pluck('team_player.player_id');

        return $club->players()
            ->active()
            ->whereNotIn('id', $takenIds)
            ->orderBy('name')
            ->get(['id', 'ulid', 'name', 'jersey_number', 'position'])
            ->map(fn (Player $p) => [
                'id' => $p->id,
                'ulid' => $p->ulid,
                'name' => $p->name,
                'jersey_number' => $p->jersey_number,
                'position' => $p->position?->value,
            ]);
    }

    /** @return array<string, mixed> */
    private function presentTeam(Team $team, bool $full = false): array
    {
        $data = [
            'id' => $team->id,
            'ulid' => $team->ulid,
            'name' => $team->name,
            'color' => $team->color,
            'bio' => $team->bio,
            'is_tournament' => $team->is_tournament,
            'logo_url' => $team->logo_url,
            'cover_url' => $team->cover_url,
            'season' => [
                'ulid' => $team->season->ulid,
                'name' => $team->season->name,
                'is_active' => $team->season->isActive(),
            ],
            'coach' => $team->coach ? $this->presentPlayer($team->coach) : null,
            'captain' => $team->captain ? $this->presentPlayer($team->captain) : null,
            'players_count' => $team->players->count(),
        ];

        if ($full) {
            $data['players'] = $team->players->map(fn (Player $p) => $this->presentPlayer($p))->values();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function presentPlayer(Player $player): array
    {
        return [
            'id' => $player->id,
            'ulid' => $player->ulid,
            'name' => $player->display_name,
            'jersey_number' => $player->jersey_number,
            'position' => $player->position?->value,
            'photo_url' => $player->photo_url,
        ];
    }
}
