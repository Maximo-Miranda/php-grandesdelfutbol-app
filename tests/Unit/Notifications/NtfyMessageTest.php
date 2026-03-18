<?php

use App\Notifications\Messages\NtfyMessage;

test('ntfy message has default priority of 3', function () {
    $message = NtfyMessage::create('Test body');

    expect($message->getPriority())->toBe(3);
});

test('ntfy message fluent api builds correct array', function () {
    $message = NtfyMessage::create('Body text')
        ->title('My Title')
        ->priority(4)
        ->tags('soccer,tada')
        ->click('https://example.com')
        ->markdown()
        ->action('View', 'https://example.com/view');

    $array = $message->toArray();

    expect($array['message'])->toBe('Body text')
        ->and($array['title'])->toBe('My Title')
        ->and($array['priority'])->toBe(4)
        ->and($array['tags'])->toBe(['soccer', 'tada'])
        ->and($array['click'])->toBe('https://example.com')
        ->and($array['markdown'])->toBeTrue()
        ->and($array['actions'])->toHaveCount(1)
        ->and($array['actions'][0])->toBe([
            'action' => 'view',
            'label' => 'View',
            'url' => 'https://example.com/view',
        ]);
});

test('ntfy message omits optional fields when not set', function () {
    $message = NtfyMessage::create('Simple message');

    $array = $message->toArray();

    expect($array)->toHaveKey('message')
        ->and($array)->toHaveKey('priority')
        ->and($array)->not->toHaveKey('title')
        ->and($array)->not->toHaveKey('tags')
        ->and($array)->not->toHaveKey('click')
        ->and($array)->not->toHaveKey('markdown')
        ->and($array)->not->toHaveKey('actions');
});

test('ntfy message getters return correct values', function () {
    $message = NtfyMessage::create('Body')
        ->title('Title')
        ->priority(5);

    expect($message->getBody())->toBe('Body')
        ->and($message->getTitle())->toBe('Title')
        ->and($message->getPriority())->toBe(5);
});

test('ntfy message static create returns instance', function () {
    $message = NtfyMessage::create('Hello');

    expect($message)->toBeInstanceOf(NtfyMessage::class)
        ->and($message->getBody())->toBe('Hello');
});
