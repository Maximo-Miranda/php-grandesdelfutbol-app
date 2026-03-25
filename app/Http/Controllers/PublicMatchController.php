<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Scopes\ClubScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicMatchController extends Controller
{
    public function show(string $shareToken): Response
    {
        $match = FootballMatch::query()
            ->withoutGlobalScope(ClubScope::class)
            ->where('share_token', $shareToken)
            ->with('club', 'field', 'attendances.player', 'events.player', 'events.relatedPlayer', 'videoUpload')
            ->firstOrFail();

        $videoUpload = $match->videoUpload;
        $s3VideoUrl = $videoUpload?->best_resolution && ! $videoUpload->youtube_video_id
            ? Storage::disk('s3')->temporaryUrl($videoUpload->s3_path, now()->addMinutes(30))
            : null;

        $user = Auth::user();
        $isMember = $user && $match->club->members()->where('user_id', $user->id)->exists();

        return Inertia::render('matches/Public', [
            'match' => $match,
            'isMember' => $isMember,
            's3VideoUrl' => $s3VideoUrl,
        ]);
    }
}
