<?php

namespace App\Channels;

use App\Models\User;
use App\Notifications\Messages\NtfyMessage;
use App\Services\NtfyService;
use Illuminate\Notifications\Notification;

class NtfyChannel
{
    public function __construct(private NtfyService $ntfyService) {}

    public function send(User $notifiable, Notification $notification): void
    {
        if (! $notifiable->hasNtfyEnabled()) {
            return;
        }

        /** @var NtfyMessage $message */
        $message = $notification->toNtfy($notifiable);

        $this->ntfyService->publish($notifiable, $message->toArray());
    }
}
