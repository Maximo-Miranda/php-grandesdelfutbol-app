<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollection;
use App\Models\PlayerProfile;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerProfileController extends Controller
{
    public function __construct(private AttachmentService $attachmentService) {}

    public function edit(Request $request): Response
    {
        $profile = $request->user()->playerProfile ?? new PlayerProfile;

        return Inertia::render('profile/PlayerProfile', [
            'profile' => $profile,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nickname' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'preferred_position' => ['nullable', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $profile = $request->user()->playerProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            collect($validated)->except('photo')->toArray(),
        );

        if ($request->hasFile('photo')) {
            $this->attachmentService->upload($profile, $request->file('photo'), AttachmentCollection::Photo);
        }

        return back()->with('success', 'Profile updated.');
    }
}
