<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollection;
use App\Models\Club;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicExploreController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q'));

        $query = Club::query()
            ->where('is_public', true)
            ->whereNotNull('slug');

        if ($search !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%']);
        }

        $clubs = $query
            ->with(['attachments' => fn ($q) => $q->where('collection', AttachmentCollection::Logo)])
            ->withCount([
                'matches as completed_matches_count' => fn ($q) => $q->completed(),
                'matches as upcoming_matches_count' => fn ($q) => $q->upcoming(),
                'players' => fn ($q) => $q->where('is_active', true),
            ])
            ->withMax('matches as last_activity_at', 'scheduled_at')
            ->orderByRaw('last_activity_at DESC NULLS LAST')
            ->orderBy('name')
            ->simplePaginate(12)
            ->withQueryString()
            ->through(fn (Club $club) => [
                'ulid' => $club->ulid,
                'slug' => $club->slug,
                'name' => $club->name,
                'description' => $club->description,
                'logo_url' => $club->logo_url,
                'completed_matches_count' => $club->completed_matches_count,
                'upcoming_matches_count' => $club->upcoming_matches_count,
                'players_count' => $club->players_count,
            ]);

        return Inertia::render('explore/Public', [
            'clubs' => $clubs,
            'search' => $search,
            'appUrl' => config('app.url'),
        ]);
    }
}
