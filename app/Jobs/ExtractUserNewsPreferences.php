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
            $extracted = ExtractNewsPreferences::make()
                ->prompt($this->freeText)
                ->structured();
        } catch (\Throwable $e) {
            Log::warning("Failed to extract preferences for user {$this->user->id}: {$e->getMessage()}");

            return;
        }

        $teams = $extracted['teams'] ?? [];
        $competitions = $extracted['competitions'] ?? [];
        $topics = $extracted['topics'] ?? [];

        $this->ensureEntriesExist($teams, NewsDictionaryType::Team);
        $this->ensureEntriesExist($competitions, NewsDictionaryType::Competition);
        $this->ensureEntriesExist($topics, NewsDictionaryType::Topic);

        $preference->update([
            'teams' => $this->mergeUnique($preference->teams, $teams),
            'competitions' => $this->mergeUnique($preference->competitions, $competitions),
            'topics' => $this->mergeUnique($preference->topics, $topics),
            'ai_extracted_entities' => $extracted,
        ]);
    }

    /** @param  list<string>  $keys */
    private function ensureEntriesExist(array $keys, NewsDictionaryType $type): void
    {
        if ($keys === []) {
            return;
        }

        $existing = NewsDictionaryEntry::whereIn('key', $keys)->pluck('key')->all();

        foreach (array_diff($keys, $existing) as $key) {
            $label = Str::of($key)->replace('_', ' ')->title()->toString();

            NewsDictionaryEntry::create([
                'type' => $type,
                'key' => $key,
                'label' => $label,
                'aliases' => array_values(array_unique([$label, str_replace('_', ' ', $key)])),
                'is_active' => true,
                'source' => 'ai',
            ]);
        }
    }

    /**
     * @param  list<string>|null  $existing
     * @param  list<string>  $new
     * @return list<string>|null
     */
    private function mergeUnique(?array $existing, array $new): ?array
    {
        $merged = array_values(array_unique([...$existing ?? [], ...$new]));

        return $merged ?: null;
    }
}
