<?php

namespace App\Jobs;

use App\Ai\Agents\ExtractNewsPreferences;
use App\Enums\NewsDictionaryType;
use App\Models\NewsDictionaryEntry;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExtractUserNewsPreferences implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 2;

    public function __construct(public User $user, public string $freeText) {}

    public function handle(): void
    {
        $preference = $this->user->newsPreference;

        if (! $preference) {
            return;
        }

        try {
            $response = ExtractNewsPreferences::make()
                ->prompt($this->freeText);
        } catch (\Throwable $e) {
            Log::warning("Failed to extract preferences for user {$this->user->id}: {$e->getMessage()}");

            return;
        }

        $teams = $this->normalizeKeys($response['teams'] ?? [], NewsDictionaryType::Team);
        $competitions = $this->normalizeKeys($response['competitions'] ?? [], NewsDictionaryType::Competition);
        $topics = $this->normalizeKeys($response['topics'] ?? [], NewsDictionaryType::Topic);

        $preference->update([
            'ai_extracted_entities' => compact('teams', 'competitions', 'topics'),
        ]);
    }

    /**
     * Resolve AI-generated keys against existing dictionary entries. If a key
     * already exists as an entry, keep it. If it matches an existing entry's
     * alias, swap it for that entry's canonical key. Otherwise, create a new
     * entry so the feed can filter by it in the future.
     *
     * @param  list<string>  $rawKeys
     * @return list<string>
     */
    private function normalizeKeys(array $rawKeys, NewsDictionaryType $type): array
    {
        if ($rawKeys === []) {
            return [];
        }

        $dictionary = NewsDictionaryEntry::getDictionary();
        $entries = $dictionary[$type->value] ?? [];
        $normalized = [];

        foreach ($rawKeys as $key) {
            // Already a known key — use as-is.
            if (isset($entries[$key])) {
                $normalized[] = $key;

                continue;
            }

            // Search aliases for a match (Gemini says "junior", dictionary has
            // "junior_barranquilla" with alias "junior").
            $humanized = str_replace('_', ' ', $key);
            $resolved = null;

            foreach ($entries as $entryKey => $aliases) {
                foreach ($aliases as $alias) {
                    if (mb_strtolower($alias) === mb_strtolower($key) || mb_strtolower($alias) === mb_strtolower($humanized)) {
                        $resolved = $entryKey;
                        break 2;
                    }
                }
            }

            if ($resolved !== null) {
                $normalized[] = $resolved;

                continue;
            }

            // New entity not in the dictionary — create it so articles can
            // match in the future when they're categorized.
            $label = Str::of($key)->replace('_', ' ')->title()->toString();

            NewsDictionaryEntry::create([
                'type' => $type,
                'key' => $key,
                'label' => $label,
                'aliases' => array_values(array_unique([$label, str_replace('_', ' ', $key)])),
                'is_active' => true,
                'source' => 'ai',
            ]);

            $normalized[] = $key;
        }

        NewsDictionaryEntry::clearCache();

        return array_values(array_unique($normalized));
    }
}
