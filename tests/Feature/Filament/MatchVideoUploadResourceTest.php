<?php

use App\Filament\Resources\MatchVideoUploadResource\Pages\ListMatchVideoUploads;
use App\Jobs\UploadMatchToYouTube;
use App\Models\MatchVideoUpload;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('can load the list page', function (): void {
    $uploads = MatchVideoUpload::factory()->count(3)->create();

    Livewire::test(ListMatchVideoUploads::class)
        ->assertOk()
        ->assertCanSeeTableRecords($uploads);
});

it('can search by match title', function (): void {
    $target = MatchVideoUpload::factory()->create();
    $other = MatchVideoUpload::factory()->create();

    Livewire::test(ListMatchVideoUploads::class)
        ->searchTable($target->match->title)
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('can filter by status', function (): void {
    $ready = MatchVideoUpload::factory()->ready()->create();
    $failed = MatchVideoUpload::factory()->failed()->create();

    Livewire::test(ListMatchVideoUploads::class)
        ->filterTable('status', 'ready')
        ->assertCanSeeTableRecords([$ready])
        ->assertCanNotSeeTableRecords([$failed]);
});

it('can sort by created_at', function (): void {
    $older = MatchVideoUpload::factory()->create(['created_at' => now()->subDay()]);
    $newer = MatchVideoUpload::factory()->create(['created_at' => now()]);

    Livewire::test(ListMatchVideoUploads::class)
        ->sortTable('created_at', 'asc')
        ->assertCanSeeTableRecords([$older, $newer], inOrder: true);
});

it('dispatches youtube upload job immediately when quota is available', function (): void {
    Queue::fake([UploadMatchToYouTube::class]);

    $upload = MatchVideoUpload::factory()->ready()->create([
        'best_resolution' => '1080p',
        'youtube_video_id' => null,
        'youtube_upload_requested_at' => null,
    ]);

    Livewire::test(ListMatchVideoUploads::class)
        ->callAction(TestAction::make('upload_to_youtube')->table($upload))
        ->assertNotified('Subida a YouTube iniciada');

    Queue::assertPushed(UploadMatchToYouTube::class);

    expect($upload->fresh())
        ->youtube_upload_requested_at->not->toBeNull()
        ->error_message->toBeNull();
});

it('shows error when youtube quota is exhausted on single upload', function (): void {
    Queue::fake([UploadMatchToYouTube::class]);

    Cache::put('youtube-daily-uploads:'.now()->format('Y-m-d'), 6, now()->endOfDay());

    $upload = MatchVideoUpload::factory()->ready()->create([
        'best_resolution' => '1080p',
        'youtube_video_id' => null,
        'youtube_upload_requested_at' => null,
    ]);

    Livewire::test(ListMatchVideoUploads::class)
        ->callAction(TestAction::make('upload_to_youtube')->table($upload))
        ->assertNotified();

    Queue::assertNotPushed(UploadMatchToYouTube::class);

    expect($upload->fresh()->youtube_upload_requested_at)->toBeNull();
});

it('dispatches bulk youtube uploads respecting quota limit', function (): void {
    Queue::fake([UploadMatchToYouTube::class]);

    Cache::put('youtube-daily-uploads:'.now()->format('Y-m-d'), 4, now()->endOfDay());

    $uploads = MatchVideoUpload::factory()->ready()->count(3)->create([
        'best_resolution' => '1080p',
        'youtube_video_id' => null,
        'youtube_upload_requested_at' => null,
    ]);

    Livewire::test(ListMatchVideoUploads::class)
        ->selectTableRecords($uploads->pluck('id')->toArray())
        ->callAction(TestAction::make('queue_youtube')->table()->bulk())
        ->assertNotified();

    Queue::assertPushed(UploadMatchToYouTube::class, 2);

    $dispatched = $uploads->fresh()->filter(fn ($u) => $u->youtube_upload_requested_at !== null);

    expect($dispatched)->toHaveCount(2);
});

it('shows error when bulk action has zero quota', function (): void {
    Queue::fake([UploadMatchToYouTube::class]);

    Cache::put('youtube-daily-uploads:'.now()->format('Y-m-d'), 6, now()->endOfDay());

    $uploads = MatchVideoUpload::factory()->ready()->count(2)->create([
        'best_resolution' => '1080p',
        'youtube_video_id' => null,
        'youtube_upload_requested_at' => null,
    ]);

    Livewire::test(ListMatchVideoUploads::class)
        ->selectTableRecords($uploads->pluck('id')->toArray())
        ->callAction(TestAction::make('queue_youtube')->table()->bulk())
        ->assertNotified();

    Queue::assertNotPushed(UploadMatchToYouTube::class);
});

it('can retry a failed youtube upload', function (): void {
    Queue::fake([UploadMatchToYouTube::class]);

    $upload = MatchVideoUpload::factory()->ready()->create([
        'best_resolution' => '1080p',
        'youtube_video_id' => null,
        'error_message' => 'Se alcanzó el límite diario de YouTube.',
    ]);

    Livewire::test(ListMatchVideoUploads::class)
        ->callAction(TestAction::make('retry_youtube')->table($upload))
        ->assertNotified('Reintento de subida a YouTube iniciado');

    Queue::assertPushed(UploadMatchToYouTube::class);

    expect($upload->fresh())
        ->error_message->toBeNull()
        ->youtube_upload_requested_at->not->toBeNull();
});

it('shows error when retrying with exhausted quota', function (): void {
    Queue::fake([UploadMatchToYouTube::class]);

    Cache::put('youtube-daily-uploads:'.now()->format('Y-m-d'), 6, now()->endOfDay());

    $upload = MatchVideoUpload::factory()->ready()->create([
        'best_resolution' => '1080p',
        'youtube_video_id' => null,
        'error_message' => 'Error previo',
    ]);

    Livewire::test(ListMatchVideoUploads::class)
        ->callAction(TestAction::make('retry_youtube')->table($upload))
        ->assertNotified();

    Queue::assertNotPushed(UploadMatchToYouTube::class);

    expect($upload->fresh()->error_message)->toBe('Error previo');
});
