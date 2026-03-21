<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Enums\PlayerPosition;
use App\Http\Requests\Match\StoreMatchRequest;
use App\Http\Requests\Match\UpdateMatchRequest;
use App\Jobs\PublishClubNtfy;
use App\Models\Club;
use App\Models\FootballMatch;
use App\Notifications\MatchVideoUploadedNotification;
use App\Services\MatchService;
use App\Services\ReelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class MatchController extends Controller
{
    public function __construct(private MatchService $matchService) {}

    public function index(Request $request, Club $club): Response
    {
        Gate::authorize('viewAny', [FootballMatch::class, $club]);

        $filter = $request->query('filter') === 'all'
            ? 'all'
            : ($request->enum('filter', MatchStatus::class) ?? MatchStatus::Upcoming);

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

        $match->load('field.venue', 'attendances.player.user.playerProfile', 'events.player.user.playerProfile', 'events.relatedPlayer');

        if ($match->status === MatchStatus::InProgress && $isAdmin) {
            return Inertia::render('clubs/matches/Live', $this->liveProps($club, $match));
        }

        if ($match->status === MatchStatus::Completed) {
            return Inertia::render('clubs/matches/Summary', [
                ...$this->summaryProps($club, $match, $user, $isAdmin),
                'reels' => fn () => $match->reels()
                    ->whereIn('source', ['auto', 'manual'])
                    ->with('player', 'event', 'media')
                    ->orderBy('start_second')
                    ->simplePaginate(10, pageName: 'reels'),
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

        return Inertia::render('clubs/matches/Edit', [
            'club' => $club,
            'match' => $match,
            'venues' => $club->venues()->with('fields')->where('is_active', true)->get(),
        ]);
    }

    public function update(UpdateMatchRequest $request, Club $club, FootballMatch $match): RedirectResponse
    {
        $oldYoutubeUrl = $match->youtube_url;

        $match->update($request->validated());

        if ($match->wasChanged('youtube_url') && $match->youtube_url !== null) {
            $this->handleYoutubeUrlChange($club, $match, $oldYoutubeUrl);
        }

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

        $match->load('field.venue', 'attendances.player.user.playerProfile', 'events.player.user.playerProfile', 'events.relatedPlayer');

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

    /**
     * @param  \App\Models\User  $user
     * @return array<string, mixed>
     */
    private function summaryProps(Club $club, FootballMatch $match, $user, bool $isAdmin): array
    {
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
        ];
    }

    private function handleYoutubeUrlChange(Club $club, FootballMatch $match, ?string $oldYoutubeUrl): void
    {
        if ($oldYoutubeUrl === null) {
            $notification = new MatchVideoUploadedNotification($match);

            $members = $club->approvedMemberUsersWithPush();
            if ($members->isNotEmpty()) {
                Notification::send($members, $notification);
            }

            PublishClubNtfy::dispatch($club, $notification->toNtfyPayload());
        } else {
            $match->update([
                'video_path' => null,
                'video_duration_seconds' => null,
            ]);
        }

        if ($match->status === MatchStatus::Completed) {
            app(ReelService::class)->generateReelsForMatch($match, force: $oldYoutubeUrl !== null);
        }
    }
}
