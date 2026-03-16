<?php

namespace App\Jobs;

use App\Models\Club;
use App\Services\AttachmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;

class DeleteClub implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $clubId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->clubId)];
    }

    public function handle(AttachmentService $attachmentService): void
    {
        $club = Club::find($this->clubId);

        if (! $club) {
            return;
        }

        DB::transaction(function () use ($club, $attachmentService) {
            $club->attachments->each(fn ($attachment) => $attachmentService->delete($attachment));

            $club->delete();
        });
    }
}
