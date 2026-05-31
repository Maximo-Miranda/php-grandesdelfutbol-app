<?php

use App\Enums\VideoProcessingStage;
use App\Models\MatchVideoUpload;

test('originalExtension returns lowercased extension of the uploaded file', function () {
    $upload = MatchVideoUpload::factory()->make(['original_filename' => 'partido.MOV']);

    expect($upload->originalExtension())->toBe('mov');
});

test('originalExtension defaults to mp4 when filename has no extension or is null', function () {
    expect(MatchVideoUpload::factory()->make(['original_filename' => 'partido'])->originalExtension())->toBe('mp4')
        ->and(MatchVideoUpload::factory()->make(['original_filename' => null])->originalExtension())->toBe('mp4');
});

test('markProcessingStage records stage and heartbeat', function () {
    $upload = MatchVideoUpload::factory()->create(['processing_stage' => null, 'processing_heartbeat_at' => null]);

    $upload->markProcessingStage(VideoProcessingStage::Receiving);

    $fresh = $upload->fresh();
    expect($fresh->processing_stage)->toBe(VideoProcessingStage::Receiving)
        ->and($fresh->processing_heartbeat_at)->not->toBeNull();
});
