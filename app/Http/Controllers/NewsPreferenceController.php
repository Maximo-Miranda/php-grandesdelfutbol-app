<?php

namespace App\Http\Controllers;

use App\Enums\NewsDictionaryType;
use App\Http\Requests\News\StoreNewsPreferenceRequest;
use App\Jobs\ExtractUserNewsPreferences;
use App\Models\NewsDictionaryEntry;
use App\Models\UserNewsPreference;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NewsPreferenceController extends Controller
{
    public function create(): Response
    {
        $user = auth()->user();

        $competitions = NewsDictionaryEntry::query()
            ->where('type', NewsDictionaryType::Competition)
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['key', 'label']);

        return Inertia::render('news/Preferences', [
            'preference' => $user->newsPreference,
            'availableCompetitions' => $competitions,
        ]);
    }

    public function store(StoreNewsPreferenceRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $data = $request->validated();
        $existingPreference = $user->newsPreference;

        // When the user clears the free-text prompt, also wipe the entities
        // that the AI extracted from it. Otherwise stale teams/topics remain
        // in the DB and keep filtering the feed even after the user removed them.
        $hadFreeText = filled($existingPreference?->free_text_input);
        $hasFreeText = filled($data['free_text_input'] ?? null);

        if ($hadFreeText && ! $hasFreeText) {
            $data['teams'] = null;
            $data['topics'] = null;
            $data['ai_extracted_entities'] = null;
        }

        UserNewsPreference::updateOrCreate(
            ['user_id' => $user->id],
            [...$data, 'onboarding_completed' => true],
        );

        if ($hasFreeText) {
            ExtractUserNewsPreferences::dispatch($user, $data['free_text_input']);
        }

        return redirect()->route('news.feed')->with('success', 'Preferencias guardadas.');
    }

    public function update(StoreNewsPreferenceRequest $request): RedirectResponse
    {
        return $this->store($request);
    }
}
