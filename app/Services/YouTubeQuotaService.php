<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class YouTubeQuotaService
{
    public function dailyLimit(): int
    {
        return config('youtube.daily_upload_limit', 6);
    }

    public function usedToday(): int
    {
        return (int) Cache::get($this->cacheKey(), 0);
    }

    public function availableSlots(): int
    {
        return max(0, $this->dailyLimit() - $this->usedToday());
    }

    public function isQuotaAvailable(): bool
    {
        return $this->availableSlots() > 0;
    }

    public function increment(): void
    {
        $key = $this->cacheKey();
        $ttl = (int) now()->diffInSeconds(now()->endOfDay());

        Cache::add($key, 0, $ttl);
        Cache::increment($key);
    }

    public function quotaLabel(): string
    {
        return "{$this->usedToday()} / {$this->dailyLimit()}";
    }

    private function cacheKey(): string
    {
        return 'youtube-daily-uploads:'.now()->format('Y-m-d');
    }
}
