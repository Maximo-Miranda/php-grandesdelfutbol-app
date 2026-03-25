<?php

namespace App\Http\Controllers;

use App\Http\Requests\VideoServiceRequest\StoreVideoServiceRequestRequest;
use App\Models\FootballMatch;
use App\Models\VideoServiceRequest;
use App\Notifications\VideoServiceRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class VideoServiceRequestController extends Controller
{
    public function store(StoreVideoServiceRequestRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($user = $request->user()) {
            $data['user_id'] = $user->id;
            $data['name'] = $data['name'] ?: $user->name;
            $data['email'] = $data['email'] ?: $user->email;
        }

        $data['club_name'] = $data['club_name'] ?? 'Anónimo';

        if (! empty($data['match_ulid'])) {
            $data['match_id'] = FootballMatch::where('ulid', $data['match_ulid'])->value('id');
        }
        unset($data['match_ulid']);

        $videoServiceRequest = VideoServiceRequest::create($data);

        $adminEmail = config('app.video_service_request_email');

        Notification::route('mail', $adminEmail)
            ->notify(new VideoServiceRequestNotification($videoServiceRequest));

        return response()->json([
            'message' => 'Solicitud enviada exitosamente.',
        ], 201);
    }
}
