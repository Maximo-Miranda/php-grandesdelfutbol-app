<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollection;
use App\Models\FootballMatch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicMatchController extends Controller
{
    public function show(string $shareToken): Response
    {
        $match = FootballMatch::anyClub()
            ->where('share_token', $shareToken)
            ->with([
                'club.attachments' => fn ($q) => $q->where('collection', AttachmentCollection::Logo),
                'field.venue:id,name,address',
                'season:id,name',
                'teamA.attachments' => fn ($q) => $q->where('collection', AttachmentCollection::TeamLogo),
                'teamB.attachments' => fn ($q) => $q->where('collection', AttachmentCollection::TeamLogo),
                'attendances.player',
                'events.player',
                'events.relatedPlayer',
                'videoUpload',
            ])
            ->firstOrFail();

        $videoUpload = $match->videoUpload;
        $s3VideoUrl = $videoUpload?->best_resolution && ! $videoUpload->youtube_video_id
            ? Storage::disk('s3')->temporaryUrl($videoUpload->s3_path, now()->addMinutes(30))
            : null;

        $user = Auth::user();
        $isMember = $user && $match->club->members()->where('user_id', $user->id)->exists();

        $match->setAttribute('team_a_logo_url', $match->teamA?->logo_url);
        $match->setAttribute('team_b_logo_url', $match->teamB?->logo_url);

        return Inertia::render('matches/Public', [
            'match' => $match,
            'club' => [
                'ulid' => $match->club->ulid,
                'slug' => $match->club->slug,
                'name' => $match->club->name,
                'logo_url' => $match->club->logo_url,
                'is_public' => (bool) $match->club->is_public,
            ],
            'isMember' => $isMember,
            's3VideoUrl' => $s3VideoUrl,
            'appUrl' => config('app.url'),
        ]);
    }
}
