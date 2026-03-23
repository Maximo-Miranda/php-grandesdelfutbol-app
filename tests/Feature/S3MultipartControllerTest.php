<?php

use App\Models\User;

it('requires authentication for s3 multipart endpoints', function () {
    $this->postJson('/s3/multipart', [
        'filename' => 'test.mp4',
        'content_type' => 'video/mp4',
    ])->assertUnauthorized();
});

it('creates a multipart upload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/s3/multipart', [
            'filename' => 'match-video.mp4',
            'content_type' => 'video/mp4',
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['uploadId', 'key']);
});

it('validates required fields on create', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/s3/multipart', [])
        ->assertUnprocessable();
});

it('signs a part for upload', function () {
    $user = User::factory()->create();

    // First create a multipart upload
    $createResponse = $this->actingAs($user)
        ->postJson('/s3/multipart', [
            'filename' => 'test.mp4',
            'content_type' => 'video/mp4',
        ]);

    $uploadId = $createResponse->json('uploadId');
    $key = $createResponse->json('key');

    $this->actingAs($user)
        ->getJson("/s3/multipart/{$uploadId}/1?key={$key}")
        ->assertSuccessful()
        ->assertJsonStructure(['url', 'headers']);
});

it('returns error when key is missing on signPart', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/s3/multipart/fake-upload-id/1')
        ->assertStatus(400);
});
