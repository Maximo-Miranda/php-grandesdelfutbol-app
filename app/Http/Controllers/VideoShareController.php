<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\FootballMatch;
use App\Models\Scopes\ClubScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class VideoShareController extends Controller
{
    public function generate(Club $club, FootballMatch $match): JsonResponse
    {
        Gate::authorize('update', $match);

        $videoUpload = $match->videoUpload;

        if (! $videoUpload || ! $videoUpload->s3_path || ! $videoUpload->best_resolution) {
            return response()->json(['error' => 'No hay video disponible para compartir.'], 422);
        }

        $hours = config('youtube.video_share_hours', 24);
        $expiresAt = now()->addHours($hours);

        $url = URL::temporarySignedRoute(
            'video.share',
            $expiresAt,
            ['matchUlid' => $match->ulid],
        );

        return response()->json([
            'url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function show(Request $request, string $matchUlid): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Este enlace ha expirado o no es válido.');
        }

        $match = FootballMatch::query()
            ->withoutGlobalScope(ClubScope::class)
            ->where('ulid', $matchUlid)
            ->with('club', 'videoUpload')
            ->firstOrFail();

        $videoUpload = $match->videoUpload;
        $youtubeEmbedUrl = $videoUpload?->youtube_embed_url;
        $s3VideoUrl = null;

        if ($videoUpload?->s3_path && $videoUpload?->best_resolution && ! $youtubeEmbedUrl) {
            $s3VideoUrl = Storage::disk('s3')->temporaryUrl(
                $videoUpload->s3_path,
                now()->addMinutes(30),
            );
        }

        return Inertia::render('matches/VideoShare', [
            'match' => $match,
            'youtubeEmbedUrl' => $youtubeEmbedUrl,
            's3VideoUrl' => $s3VideoUrl,
        ]);
    }
}
