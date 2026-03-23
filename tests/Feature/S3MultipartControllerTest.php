<?php

use App\Models\User;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;

beforeEach(function () {
    $mock = Mockery::mock(S3Client::class);

    $mock->shouldReceive('createMultipartUpload')
        ->andReturn(new Result(['UploadId' => 'test-upload-id']));

    $mock->shouldReceive('getCommand')
        ->andReturn(Mockery::mock(CommandInterface::class));

    $presignedRequest = Mockery::mock(RequestInterface::class);
    $presignedRequest->shouldReceive('getUri')
        ->andReturn(new Uri('https://s3.example.com/presigned-url'));

    $mock->shouldReceive('createPresignedRequest')
        ->andReturn($presignedRequest);

    $mock->shouldReceive('listParts')
        ->andReturn(new Result(['Parts' => [], 'IsTruncated' => false]));

    $mock->shouldReceive('completeMultipartUpload')
        ->andReturn(new Result(['Location' => 'https://s3.example.com/file']));

    $mock->shouldReceive('abortMultipartUpload')
        ->andReturn(new Result([]));

    app()->bind(S3Client::class, fn () => $mock);
});

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

    $this->actingAs($user)
        ->getJson('/s3/multipart/test-upload-id/1?key=uploads/test/file.mp4')
        ->assertSuccessful()
        ->assertJsonStructure(['url', 'headers']);
});

it('returns error when key is missing on signPart', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/s3/multipart/fake-upload-id/1')
        ->assertStatus(400);
});
