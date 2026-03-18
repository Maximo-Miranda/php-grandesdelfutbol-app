<?php

namespace App\Jobs;

use App\Models\Club;
use App\Services\NtfyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishClubNtfy implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Club $club,
        public array $payload,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(NtfyService $ntfyService): void
    {
        $ntfyService->publish($this->club, $this->payload);
    }
}
