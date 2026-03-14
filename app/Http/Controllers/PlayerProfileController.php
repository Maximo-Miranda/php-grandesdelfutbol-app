<?php

namespace App\Http\Controllers;

use App\Enums\PlayerPosition;
use App\Http\Requests\UpdatePlayerProfileRequest;
use App\Models\PlayerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('profile/PlayerProfile', [
            'profile' => $request->user()->playerProfile ?? new PlayerProfile,
            'positions' => collect(PlayerPosition::cases())->map(fn (PlayerPosition $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function update(UpdatePlayerProfileRequest $request): RedirectResponse
    {
        $profile = $request->user()->playerProfile()->updateOrCreate(
            [],
            $request->safe()->except(['photo']),
        );

        if ($request->hasFile('photo')) {
            $profile->addMediaFromRequest('photo')->toMediaCollection('photo');
        }

        return back()->with('success', 'Perfil actualizado.');
    }
}
