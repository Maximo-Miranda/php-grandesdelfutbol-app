<?php

use App\Services\ReelService;

test('calculateClipWindow returns correct start and end', function () {
    $service = new ReelService;

    $result = $service->calculateClipWindow(45, 30, 0);

    expect($result['start'])->toBe(2715) // (45*60 + 30) - 15
        ->and($result['end'])->toBe(2740); // (45*60 + 30) + 10
});

test('calculateClipWindow respects video offset', function () {
    $service = new ReelService;

    $result = $service->calculateClipWindow(10, 0, 120);

    expect($result['start'])->toBe(705) // (10*60 + 0 + 120) - 15
        ->and($result['end'])->toBe(730); // (10*60 + 0 + 120) + 10
});

test('calculateClipWindow start does not go below zero', function () {
    $service = new ReelService;

    $result = $service->calculateClipWindow(0, 5, 0);

    expect($result['start'])->toBe(0)
        ->and($result['end'])->toBe(15); // 5 + 10
});

test('calculateClipWindow duration is always 25 seconds unless clamped', function () {
    $service = new ReelService;

    $result = $service->calculateClipWindow(20, 0, 0);

    expect($result['end'] - $result['start'])->toBe(25);
});

test('calculateClipWindow at minute zero second zero with offset zero', function () {
    $service = new ReelService;

    $result = $service->calculateClipWindow(0, 0, 0);

    expect($result['start'])->toBe(0)
        ->and($result['end'])->toBe(10);
});
