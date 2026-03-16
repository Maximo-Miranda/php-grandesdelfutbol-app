<?php

use App\Channels\NtfyChannel;
use App\Models\User;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

test('ntfy channel sends to correct url and topic', function () {
    Http::fake();

    config(['services.ntfy.url' => 'https://push.example.com']);
    config(['services.ntfy.token' => 'test-token']);

    $user = User::factory()->withNtfy()->create();
    $topic = $user->ntfyTopic();

    $notification = new class extends Notification
    {
        public function toNtfy(object $notifiable): NtfyMessage
        {
            return NtfyMessage::create('Test message')
                ->title('Test title');
        }
    };

    $channel = app(NtfyChannel::class);
    $channel->send($user, $notification);

    Http::assertSent(function ($request) use ($topic) {
        return str_contains($request->url(), "https://push.example.com/{$topic}")
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['message'] === 'Test message'
            && $request['title'] === 'Test title';
    });
});

test('ntfy channel skips when ntfy is not enabled', function () {
    Http::fake();

    $user = User::factory()->create();

    $notification = new class extends Notification
    {
        public function toNtfy(object $notifiable): NtfyMessage
        {
            return NtfyMessage::create('Test');
        }
    };

    $channel = app(NtfyChannel::class);
    $channel->send($user, $notification);

    Http::assertNothingSent();
});

test('ntfy channel does not throw on http failure', function () {
    Http::fake(fn () => Http::response('Error', 500));

    $user = User::factory()->withNtfy()->create();

    $notification = new class extends Notification
    {
        public function toNtfy(object $notifiable): NtfyMessage
        {
            return NtfyMessage::create('Test');
        }
    };

    $channel = app(NtfyChannel::class);

    expect(fn () => $channel->send($user, $notification))->not->toThrow(Exception::class);
});
