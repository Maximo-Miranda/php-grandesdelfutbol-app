<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Enums\PlayerPosition;
use App\Http\Requests\Match\StoreMatchRequest;
use App\Http\Requests\Match\UpdateMatchRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\User;
use App\Services\MatchService;
use App\Services\MatchStatService;
use App\Services\SeasonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MatchController extends Controller
{
    public function __construct(
        private MatchService $matchService,
        private SeasonService $seasonService,
        private MatchStatService $statService,
    ) {}

    /** @return array<int, array<string, mixed>> */
    private function teamsForActiveSeason(Club $club): array
    {
        $season = $this->seasonService->activeFor($club);

        return Team::query()->where('season_id', $season->id)
            ->with('attachments')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $t) => [
                'id' => $t->id,
                'ulid' => $t->ulid,
                'name' => $t->name,
                'color' => $t->color,
                'logo_url' => $t->logo_url,
            ])
            ->all();
    }

    public function index(Request $request, Club $club): Response
    {
        Gate::authorize('viewAny', [FootballMatch::class, $club]);

        $filter = $request->enum('filter', MatchStatus::class) ?? 'all';

        $query = $club->matches()
            ->with(['field', 'season:id,name', 'teamA.attachments', 'teamB.attachments'])
            ->withCount('attendances');

        if ($filter === MatchStatus::Upcoming) {
            $query->whereIn('status', [MatchStatus::Upcoming, MatchStatus::InProgress])
                ->orderBy('scheduled_at');
        } elseif ($filter === MatchStatus::Completed) {
            $query->where('status', MatchStatus::Completed)
                ->orderByDesc('scheduled_at');
        } else {
            $query->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [
                MatchStatus::Upcoming->value,
                MatchStatus::InProgress->value,
            ])
                ->orderByRaw('CASE WHEN status IN (?, ?) THEN scheduled_at END ASC', [
                    MatchStatus::Upcoming->value,
                    MatchStatus::InProgress->value,
                ])
                ->orderByRaw('CASE WHEN status NOT IN (?, ?) THEN scheduled_at END DESC', [
                    MatchStatus::Upcoming->value,
                    MatchStatus::InProgress->value,
                ]);
        }

        return Inertia::render('clubs/matches/Index', [
            'club' => $club,
            'filter' => $filter instanceof MatchStatus ? $filter->value : $filter,
            'matches' => Inertia::scroll(fn () => $query->simplePaginate(15)),
        ]);
    }

    public function create(Club $club): Response
    {
        Gate::authorize('create', [FootballMatch::class, $club]);

        return Inertia::render('clubs/matches/Create', [
            'club' => $club,
            'venues' => $club->venues()->with('fields')->where('is_active', true)->get(),
            'defaultCancelHoursBefore' => FootballMatch::DEFAULT_CANCEL_HOURS_BEFORE,
            'availableTeams' => $this->teamsForActiveSeason($club),
        ]);
    }

    public function store(StoreMatchRequest $request, Club $club): RedirectResponse
    {
        $match = $this->matchService->createMatch($club, $request->validated());

        return redirect()->route('clubs.matches.show', [$club, $match])
            ->with('success', 'Partido creado.');
    }

    public function show(Request $request, Club $club, FootballMatch $match): Response|RedirectResponse
    {
        if (Gate::denies('view', $match) && $match->share_token) {
            return redirect()->route('match.public', $match->share_token);
        }

        Gate::authorize('view', $match);

        $user = $request->user();
        $isAdmin = $club->isAdminOrOwner($user);

        $match->load('field.venue', 'season:id,name', 'attendances.player.user.playerProfile', 'events.player.user.playerProfile', 'events.relatedPlayer', 'videoUpload', 'activeVideoServiceRequest');

        if ($match->status === MatchStatus::InProgress && $isAdmin) {
            return Inertia::render('clubs/matches/Live', $this->liveProps($club, $match));
        }

        if ($match->status === MatchStatus::Completed) {
            return Inertia::render('clubs/matches/Summary', [
                ...$this->summaryProps($club, $match, $user, $isAdmin),
                'reels' => Inertia::scroll(
                    fn () => $match->reels()
                        ->whereIn('source', ['auto', 'manual'])
                        ->with('player', 'event', 'media')
                        ->orderBy('start_second')
                        ->simplePaginate(10, pageName: 'reels'),
                ),
            ]);
        }

        $registeredPlayerIds = $match->attendances()->pluck('player_id');

        $teamRestricted = $match->isTeamRestricted();
        $isOpenCall = $match->isOpenCall();
        $tagEligibility = $this->eligibilityTagger($match, $teamRestricted);

        return Inertia::render('clubs/matches/Show', [
            'club' => $club,
            'match' => $match,
            'players' => $tagEligibility($club->players()->active()->with('user.playerProfile')->get()),
            'isAdmin' => $isAdmin,
            'isTeamRestricted' => $teamRestricted,
            'isOpenCall' => $isOpenCall,
            'registrationClosesAt' => $match->effectiveRegistrationClosesAt()->toIso8601String(),
            'balanceReport' => $isOpenCall ? $this->matchService->teamBalanceReport($match) : null,
            'myPlayer' => tap(
                $club->players()->where('user_id', $user->id)->first(),
                fn ($player) => $player ? $tagEligibility(collect([$player])) : null,
            ),
            'unregisteredPlayers' => $isAdmin
                ? Inertia::scroll(
                    fn () => tap(
                        $club->players()
                            ->active()
                            ->with('user.playerProfile')
                            ->whereNotIn('id', $registeredPlayerIds)
                            ->orderBy('name')
                            ->simplePaginate(15, pageName: 'jugadores'),
                        fn ($paginator) => $tagEligibility($paginator->getCollection()),
                    ),
                )
                : null,
        ]);
    }

    /**
     * Build a closure that tags each player in a collection with `eligible_team`
     * and `is_outsider`. Returns the collection unchanged when the match is not
     * team-restricted.
     *
     * `is_outsider` is true when the match opts into "permitir jugadores fuera
     * de la nómina" and the player is not in any roster — the player joins the
     * pool with team=null and is distributed by the draft.
     */
    private function eligibilityTagger(FootballMatch $match, bool $teamRestricted): \Closure
    {
        if (! $teamRestricted) {
            return fn ($players) => $players;
        }

        $isTournament = $match->isTournament();
        $teamALookup = $match->teamA ? array_flip($match->teamA->players()->pluck('players.id')->all()) : [];
        $teamBLookup = $match->teamB ? array_flip($match->teamB->players()->pluck('players.id')->all()) : [];
        $hasTeamA = $match->teamA !== null;
        $hasTeamB = $match->teamB !== null;
        $allowOutsiders = (bool) $match->allow_outsiders;

        return function ($players) use ($isTournament, $teamALookup, $teamBLookup, $hasTeamA, $hasTeamB, $allowOutsiders) {
            return $players->each(function ($player) use ($isTournament, $teamALookup, $teamBLookup, $hasTeamA, $hasTeamB, $allowOutsiders): void {
                $rostered = isset($teamALookup[$player->id]) || isset($teamBLookup[$player->id]);
                $player->is_outsider = $allowOutsiders && ! $rostered && ! $isTournament;

                $player->eligible_team = $this->resolveEligibleTeam(
                    $player->id,
                    $isTournament,
                    $teamALookup,
                    $teamBLookup,
                    $hasTeamA,
                    $hasTeamB,
                );
            });
        };
    }

    /**
     * @param  array<int, int>  $teamALookup
     * @param  array<int, int>  $teamBLookup
     */
    private function resolveEligibleTeam(
        int $playerId,
        bool $isTournament,
        array $teamALookup,
        array $teamBLookup,
        bool $hasTeamA,
        bool $hasTeamB,
    ): ?string {
        $inA = isset($teamALookup[$playerId]);
        $inB = isset($teamBLookup[$playerId]);

        // Tournament rosters are exclusive: pin to the player's team, never 'either'.
        if ($isTournament && $hasTeamA && $hasTeamB) {
            return match (true) {
                $inA => 'a',
                $inB => 'b',
                default => null,
            };
        }

        if ($inA) {
            return 'a';
        }

        if ($inB) {
            return 'b';
        }

        return match (true) {
            $hasTeamA && $hasTeamB => 'either',
            $hasTeamA => 'a',
            $hasTeamB => 'b',
            default => null,
        };
    }

    public function edit(Club $club, FootballMatch $match): Response
    {
        Gate::authorize('update', $match);

        $videoUpload = $match->videoUpload;

        return Inertia::render('clubs/matches/Edit', [
            'club' => $club,
            'match' => $match,
            'venues' => $club->venues()->with('fields')->where('is_active', true)->get(),
            'videoUpload' => $videoUpload,
            'embedUrl' => $videoUpload?->embed_url,
            'streamUrl' => $videoUpload?->stream_url,
            'defaultCancelHoursBefore' => FootballMatch::DEFAULT_CANCEL_HOURS_BEFORE,
            'availableTeams' => $this->teamsForActiveSeason($club),
        ]);
    }

    public function update(UpdateMatchRequest $request, Club $club, FootballMatch $match): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['single_team'])) {
            $data['team_b_id'] = null;
            $data['team_b_name'] = null;
            $data['team_b_color'] = null;
        }
        unset($data['single_team']);

        $previousMaxPlayers = $match->max_players;
        $previousMaxSubs = $match->max_substitutes;
        $previousStatus = $match->status;
        $previousTeamAId = $match->team_a_id;
        $previousTeamBId = $match->team_b_id;

        $match->update($data);
        $fresh = $match->fresh();

        $capacityChanged = $fresh->max_players !== $previousMaxPlayers || $fresh->max_substitutes !== $previousMaxSubs;
        $reactivated = $previousStatus->isFinished() && ! $fresh->status->isFinished();
        $teamsChanged = $fresh->team_a_id !== $previousTeamAId || $fresh->team_b_id !== $previousTeamBId;

        if ($capacityChanged || $reactivated) {
            $this->matchService->rebalanceCapacity($fresh);
        }

        $extraMessage = null;
        if ($teamsChanged && $fresh->isTeamRestricted()) {
            $realigned = $this->matchService->realignEventTeamsToRosters($fresh);
            if ($realigned > 0) {
                // Score depends on event teams; recalc + re-finalize stats so player goals stay consistent
                $this->statService->recalculateScore($fresh);
                if ($fresh->stats_finalized_at) {
                    $this->statService->finalizeStats($fresh->fresh());
                }
                $extraMessage = " {$realigned} ".($realigned === 1 ? 'evento se reasignó' : 'eventos se reasignaron').' al equipo correcto del jugador.';
            }
        }

        return redirect()->route('clubs.matches.show', [$club, $match])
            ->with('success', 'Partido actualizado.'.($extraMessage ?? ''));
    }

    public function destroy(Club $club, FootballMatch $match): RedirectResponse
    {
        Gate::authorize('delete', $match);

        $match->delete();

        return redirect()->route('clubs.matches.index', $club)
            ->with('success', 'Partido eliminado.');
    }

    public function live(Club $club, FootballMatch $match): Response
    {
        Gate::authorize('update', $match);

        $match->load('field', 'attendances.player.user.playerProfile', 'events.player.user.playerProfile', 'events.relatedPlayer');

        return Inertia::render('clubs/matches/Live', $this->liveProps($club, $match));
    }

    public function summary(Request $request, Club $club, FootballMatch $match): Response
    {
        Gate::authorize('view', $match);

        $match->load('field.venue', 'season:id,name', 'attendances.player.user.playerProfile', 'events.player.user.playerProfile', 'events.relatedPlayer', 'videoUpload', 'activeVideoServiceRequest');

        $user = $request->user();
        $isAdmin = $club->isAdminOrOwner($user);

        return Inertia::render('clubs/matches/Summary', [
            ...$this->summaryProps($club, $match, $user, $isAdmin),
            'reels' => Inertia::scroll(
                fn () => $match->reels()
                    ->with('player', 'event', 'requester', 'media')
                    ->orderBy('start_second')
                    ->simplePaginate(6, pageName: 'reels'),
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function liveProps(Club $club, FootballMatch $match): array
    {
        return [
            'club' => $club,
            'match' => $match,
            'players' => $club->players()->active()->with('user.playerProfile')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function summaryProps(Club $club, FootballMatch $match, User $user, bool $isAdmin): array
    {
        $videoUpload = $match->videoUpload;

        // Only sign the S3 URL when it will actually be used as the player —
        // i.e. there is no YouTube video yet. Once on YouTube, this URL is
        // discarded, so signing it on every page load/poll is wasted work.
        $s3VideoUrl = $videoUpload?->s3_path && $videoUpload->best_resolution && ! $videoUpload->youtube_video_id
            ? Storage::disk('s3')->temporaryUrl($videoUpload->s3_path, now()->addHour())
            : null;

        if ($s3VideoUrl) {
            $videoUpload->setAttribute('video_stream_url', $s3VideoUrl);
        }

        return [
            'club' => $club,
            'match' => $match,
            'isAdmin' => $isAdmin,
            'players' => $isAdmin
                ? $club->players()->active()->with('user.playerProfile')->get()
                : [],
            'positions' => $isAdmin
                ? collect(PlayerPosition::cases())->map(fn (PlayerPosition $p) => ['value' => $p->value, 'label' => $p->label()])
                : [],
            'myPlayer' => $club->players()->where('user_id', $user->id)->first(),
            's3VideoUrl' => $s3VideoUrl,
            'videoStatus' => $videoUpload ? [
                'status' => $videoUpload->status->value,
                'on_youtube' => (bool) $videoUpload->youtube_video_id,
                'stage_label' => $videoUpload->processing_stage?->label(),
            ] : null,
        ];
    }
}
