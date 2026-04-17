<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushChannel;

trait SendsMailAndWebPush
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }
}
