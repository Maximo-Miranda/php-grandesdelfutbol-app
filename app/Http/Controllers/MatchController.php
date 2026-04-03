<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Enums\PlayerPosition;
use App\Http\Requests\Match\StoreMatchRequest;
use App\Http\Requests\Match\UpdateMatchRequest;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\User;
use App\Services\MatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MatchController extends Controller
{
    public function __construct(private MatchService $matchService) {}

    public function index(Request $request, Club $club): Response
    {
        Gate::authorize('viewAny', [FootballMatch::class, $club]);

        $filter = $request->enum('filter', MatchStatus::class) ?? 'all';

        $query = $club->matches()
            ->with('field')
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

        $match->load('field.venue', 'attendances.player.user.playerProfile', 'events.player.user.playerProfile', 'events.relatedPlayer', 'videoUpload', 'activeVideoServiceRequest');

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

        return Inertia::render('clubs/matches/Show', [
            'club' => $club,
            'match' => $match,
            'players' => $club->players()->active()->with('user.playerProfile')->get(),
            'isAdmin' => $isAdmin,
            'myPlayer' => $club->players()->where('user_id', $user->id)->first(),
            'unregisteredPlayers' => $isAdmin
                ? Inertia::scroll(
                    fn () => $club->players()
                        ->active()
                        ->with('user.playerProfile')
                        ->whereNotIn('id', $registeredPlayerIds)
                        ->orderBy('name')
                        ->simplePaginate(15, pageName: 'jugadores'),
                )
                : null,
        ]);
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
        ]);
    }

    public function update(UpdateMatchRequest $request, Club $club, FootballMatch $match): RedirectResponse
    {
        $match->update($request->validated());

        return redirect()->route('clubs.matches.show', [$club, $match])
            ->with('success', 'Partido actualizado.');
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

        $match->load('field.venue', 'attendances.player.user.playerProfile', 'events.player.user.playerProfile', 'events.relatedPlayer', 'videoUpload', 'activeVideoServiceRequest');

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

        if ($videoUpload?->s3_path) {
            $videoUpload->setAttribute('drive_stream_url', Storage::disk('s3')->temporaryUrl($videoUpload->s3_path, now()->addMinutes(30)));
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
            's3VideoUrl' => $videoUpload?->best_resolution && ! $videoUpload->youtube_video_id
                ? Storage::disk('s3')->temporaryUrl($videoUpload->s3_path, now()->addMinutes(30))
                : null,
        ];
    }
}
