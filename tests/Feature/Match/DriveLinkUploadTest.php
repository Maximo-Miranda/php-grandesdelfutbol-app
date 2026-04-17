<?php

use App\Enums\VideoUploadStatus;
use App\Jobs\ProcessUploadedVideo;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\FootballMatch;
use App\Models\MatchVideoUpload;
use App\Models\User;
use App\Services\GoogleAuthService;
use App\Services\GoogleDriveService;
use Google\Service\Drive;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->club = Club::factory()->create();
    ClubMember::factory()->admin()->create(['club_id' => $this->club->id, 'user_id' => $this->user->id]);
    $this->match = FootballMatch::factory()->completed()->create(['club_id' => $this->club->id]);

    // Skip real sleep during retry backoffs.
    Sleep::fake();

    // All existing tests expect the required scope to be granted.
    mock(GoogleAuthService::class)->shouldReceive('hasScope')
        ->with(Drive::DRIVE_READONLY)->andReturn(true)->byDefault();
});

function driveException(int $code, string $reason, string $message = 'Drive error'): GoogleServiceException
{
    return new GoogleServiceException($message, $code, null, [['reason' => $reason]]);
}

test('admin can import video by pasting drive link', function () {
    Queue::fake();

    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('getFileMetadata')->once()->with('1aBcD_ef-Ghij123K')->andReturn([
        'id' => '1aBcD_ef-Ghij123K',
        'name' => 'partido.mp4',
        'size' => 2_000_000_000,
        'mimeType' => 'video/mp4',
    ]);
    $driveMock->shouldReceive('ensureClubFolder')->once()->andReturn('folder-id-123');
    $driveMock->shouldReceive('copyFile')->once()
        ->with('1aBcD_ef-Ghij123K', Mockery::type('string'), 'folder-id-123')
        ->andReturn('copied-file-id-456');
    $driveMock->shouldReceive('shareFilePublicly')->once()->with('copied-file-id-456');

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view?usp=sharing',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('match_video_uploads', [
        'football_match_id' => $this->match->id,
        'drive_file_id' => 'copied-file-id-456',
        'status' => VideoUploadStatus::Encoding->value,
        'original_filename' => 'partido.mp4',
    ]);

    Queue::assertPushed(ProcessUploadedVideo::class);
});

test('invalid drive URL is rejected with parser message', function () {
    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/drive/my-drive',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['drive_url']);
});

test('non-video mime type is rejected', function () {
    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('getFileMetadata')->once()->andReturn([
        'id' => '1aBcD_ef-Ghij123K',
        'name' => 'document.pdf',
        'size' => 1000,
        'mimeType' => 'application/pdf',
    ]);

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['drive_url']);
});

test('oversize video is rejected before copy', function () {
    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('getFileMetadata')->once()->andReturn([
        'id' => '1aBcD_ef-Ghij123K',
        'name' => 'huge.mp4',
        'size' => 30 * 1024 * 1024 * 1024, // 30 GB > 25 GB limit
        'mimeType' => 'video/mp4',
    ]);
    $driveMock->shouldNotReceive('copyFile');

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['drive_url']);
});

test('private file (404 notFound) returns share-permission message', function () {
    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('getFileMetadata')->once()
        ->andThrow(driveException(404, 'notFound', 'File not found'));

    $response = $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ]);

    $response->assertRedirect()->assertSessionHasErrors(['drive_url']);
    expect(session('errors')->get('drive_url')[0])
        ->toContain('Cualquiera con el link');
});

test('forbidden file (403 insufficientFilePermissions) returns change-permissions message', function () {
    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('getFileMetadata')->once()
        ->andThrow(driveException(403, 'insufficientFilePermissions'));

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])->assertRedirect()->assertSessionHasErrors(['drive_url']);

    expect(session('errors')->get('drive_url')[0])
        ->toContain('Cualquiera con el link');
});

test('cannotCopyFile error at copy stage gives owner-disabled-copy message', function () {
    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('getFileMetadata')->once()->andReturn([
        'id' => '1aBcD_ef-Ghij123K',
        'name' => 'partido.mp4',
        'size' => 1000,
        'mimeType' => 'video/mp4',
    ]);
    $driveMock->shouldReceive('ensureClubFolder')->once()->andReturn('folder-id-123');
    $driveMock->shouldReceive('copyFile')->once()
        ->andThrow(driveException(403, 'cannotCopyFile'));

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])->assertRedirect()->assertSessionHasErrors(['drive_url']);

    expect(session('errors')->get('drive_url')[0])
        ->toContain('no permite copiarlo');
});

test('expired auth token (401) returns reauthorize message', function () {
    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('getFileMetadata')->once()
        ->andThrow(driveException(401, 'authError'));

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])->assertRedirect()->assertSessionHasErrors(['drive_url']);

    expect(session('errors')->get('drive_url')[0])
        ->toContain('reautorice');
});

test('rate limit (403 rateLimitExceeded) retries then returns retry-later message', function () {
    $driveMock = mock(GoogleDriveService::class);
    // Rate limit is transient — retried 4 times total (1 initial + 3 backoff retries).
    $driveMock->shouldReceive('getFileMetadata')->times(4)
        ->andThrow(driveException(403, 'rateLimitExceeded'));

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])->assertRedirect()->assertSessionHasErrors(['drive_url']);

    expect(session('errors')->get('drive_url')[0])
        ->toContain('muchas solicitudes');
});

test('existing drive_file_id is deleted before replacement', function () {
    Queue::fake();
    $existingUpload = MatchVideoUpload::factory()->ready()->create([
        'football_match_id' => $this->match->id,
        'uploaded_by' => $this->user->id,
        'drive_file_id' => 'old-drive-file-id',
    ]);

    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldReceive('getFileMetadata')->once()->andReturn([
        'id' => '1aBcD_ef-Ghij123K',
        'name' => 'partido.mp4',
        'size' => 1000,
        'mimeType' => 'video/mp4',
    ]);
    $driveMock->shouldReceive('ensureClubFolder')->once()->andReturn('folder-id-123');
    $driveMock->shouldReceive('copyFile')->once()->andReturn('new-copied-file-id');
    $driveMock->shouldReceive('deleteFile')->once()->with('old-drive-file-id');
    $driveMock->shouldReceive('shareFilePublicly')->once()->with('new-copied-file-id');

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('match_video_uploads', [
        'id' => $existingUpload->id,
        'drive_file_id' => 'new-copied-file-id',
    ]);
});

test('missing drive.readonly scope returns re-authorize message', function () {
    mock(GoogleAuthService::class)->shouldReceive('hasScope')
        ->with(Drive::DRIVE_READONLY)->andReturn(false);

    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldNotReceive('getFileMetadata');

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])->assertRedirect()->assertSessionHasErrors(['drive_url']);

    expect(session('errors')->get('drive_url')[0])
        ->toContain('reautorice');
});

test('concurrent imports are blocked by idempotency lock', function () {
    $driveMock = mock(GoogleDriveService::class);
    $driveMock->shouldNotReceive('getFileMetadata');
    $driveMock->shouldNotReceive('copyFile');

    Cache::lock("drive-import:match:{$this->match->id}", 120)->get();

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])->assertRedirect()->assertSessionHasErrors(['drive_url']);

    expect(session('errors')->get('drive_url')[0])
        ->toContain('importación en curso');
});

test('transient server error triggers retry and eventually succeeds', function () {
    Queue::fake();

    $driveMock = mock(GoogleDriveService::class);

    // First 2 attempts fail with 503, 3rd succeeds.
    $driveMock->shouldReceive('getFileMetadata')
        ->times(3)
        ->andReturnUsing(function () {
            static $attempt = 0;
            $attempt++;
            if ($attempt < 3) {
                throw new GoogleServiceException('Service unavailable', 503, null, [['reason' => 'backendError']]);
            }

            return [
                'id' => '1aBcD_ef-Ghij123K',
                'name' => 'partido.mp4',
                'size' => 1_000_000,
                'mimeType' => 'video/mp4',
            ];
        });
    $driveMock->shouldReceive('ensureClubFolder')->once()->andReturn('folder-id-123');
    $driveMock->shouldReceive('copyFile')->once()->andReturn('copied-file-id-456');
    $driveMock->shouldReceive('shareFilePublicly')->once();

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])->assertRedirect()->assertSessionHas('success');
});

test('permanent error does not trigger retry', function () {
    $driveMock = mock(GoogleDriveService::class);
    // 404 should not retry: exactly 1 call.
    $driveMock->shouldReceive('getFileMetadata')->once()
        ->andThrow(driveException(404, 'notFound'));

    $this->actingAs($this->user)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])->assertRedirect()->assertSessionHasErrors(['drive_url']);
});

test('non-admin cannot use the endpoint', function () {
    $regularUser = User::factory()->create();
    ClubMember::factory()->create(['club_id' => $this->club->id, 'user_id' => $regularUser->id]);

    $this->actingAs($regularUser)
        ->post(route('clubs.matches.driveUpload.fromLink', [$this->club, $this->match]), [
            'drive_url' => 'https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view',
        ])->assertForbidden();
});
