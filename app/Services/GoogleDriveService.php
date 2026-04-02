<?php

namespace App\Services;

use App\Models\Club;
use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class GoogleDriveService
{
    public function __construct(private GoogleAuthService $authService) {}

    /**
     * Ensure a Google Drive folder exists for the given club.
     *
     * Creates the root app folder and a club-specific subfolder if they
     * don't exist yet. Stores the folder ID on the club model.
     */
    public function ensureClubFolder(Club $club): string
    {
        if ($club->google_drive_folder_id) {
            return $club->google_drive_folder_id;
        }

        $drive = $this->driveService();

        $rootFolderId = $this->findOrCreateFolder(
            $drive,
            config('youtube.drive.root_folder_name', 'Grandes del Futbol'),
        );

        $clubFolderId = $this->findOrCreateFolder(
            $drive,
            $club->name,
            $rootFolderId,
        );

        $club->update(['google_drive_folder_id' => $clubFolderId]);

        return $clubFolderId;
    }

    /**
     * Create a resumable upload session on Google Drive.
     *
     * Initiates a resumable upload and returns the session URI. The session
     * URI is valid for 7 days and can be used by the frontend to upload
     * file chunks directly to Google Drive.
     *
     * @see https://developers.google.com/workspace/drive/api/guides/manage-uploads#resumable
     */
    public function createResumableSession(string $fileName, string $mimeType, int $fileSize, string $folderId, ?string $origin = null): string
    {
        $token = $this->authService->getAccessToken();

        $headers = [
            'X-Upload-Content-Type' => $mimeType,
            'X-Upload-Content-Length' => (string) $fileSize,
        ];

        if ($origin) {
            $headers['Origin'] = $origin;
        }

        $response = Http::withToken($token['access_token'])
            ->withHeaders($headers)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', [
                'name' => $fileName,
                'parents' => [$folderId],
            ]);

        $sessionUri = $response->header('Location');

        if (empty($sessionUri)) {
            throw new RuntimeException('Failed to create Google Drive resumable upload session. Status: '.$response->status());
        }

        return $sessionUri;
    }

    /**
     * Download a file from Google Drive to a local path.
     *
     * Uses streaming to avoid loading the entire file into memory.
     *
     * @see https://developers.google.com/workspace/drive/api/reference/rest/v3/files/get
     */
    public function downloadFile(string $fileId, string $localPath): void
    {
        $drive = $this->driveService();

        /** @var StreamInterface $content */
        $content = $drive->files->get($fileId, [
            'alt' => 'media',
        ])->getBody();

        File::ensureDirectoryExists(dirname($localPath));

        $handle = fopen($localPath, 'wb');

        while (! $content->eof()) {
            fwrite($handle, $content->read(8192));
        }

        fclose($handle);
    }

    /** Delete a file from Google Drive. */
    public function deleteFile(string $fileId): void
    {
        $drive = $this->driveService();

        $drive->files->delete($fileId);
    }

    /**
     * Upload a local file to Google Drive using resumable chunked upload.
     *
     * Used to upload the 720p reels-source version back to Drive after encoding.
     *
     * @return string The Drive file ID
     */
    public function uploadFile(string $localPath, string $fileName, string $folderId): string
    {
        $client = $this->authService->authenticatedClient();
        $client->setDefer(true);

        $drive = new Drive($client);

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        /** @var RequestInterface $request */
        $request = $drive->files->create($fileMetadata);

        $chunkSize = 16 * 1024 * 1024; // 16MB chunks
        $media = new MediaFileUpload(
            $client,
            $request,
            'video/mp4',
            null,
            true,
            $chunkSize,
        );

        $media->setFileSize(File::size($localPath));

        $handle = fopen($localPath, 'rb');
        $uploadStatus = false;

        while (! $uploadStatus && ! feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            $uploadStatus = $media->nextChunk($chunk);
        }

        fclose($handle);
        $client->setDefer(false);

        if (! $uploadStatus instanceof DriveFile) {
            throw new RuntimeException('Failed to upload file to Google Drive.');
        }

        return $uploadStatus->getId();
    }

    /**
     * Share a file publicly (anyone with the link can view).
     *
     * Required for the Google Drive embed player to work.
     * Uses role=reader so viewers cannot edit or delete.
     */
    public function shareFilePublicly(string $fileId): void
    {
        $drive = $this->driveService();

        $permission = new Permission([
            'type' => 'anyone',
            'role' => 'reader',
        ]);

        $drive->permissions->create($fileId, $permission);
    }

    /**
     * Get file metadata from Google Drive.
     *
     * @return array{id: string, name: string, size: int, mimeType: string}
     */
    public function getFileMetadata(string $fileId): array
    {
        $drive = $this->driveService();

        $file = $drive->files->get($fileId, [
            'fields' => 'id,name,size,mimeType',
        ]);

        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'size' => (int) $file->getSize(),
            'mimeType' => $file->getMimeType(),
        ];
    }

    /**
     * Probe a resumable upload session to check how many bytes have been uploaded.
     *
     * This is done server-side to avoid CORS issues with the browser making
     * direct PUT requests to Google's upload endpoint.
     *
     * @see https://developers.google.com/workspace/drive/api/guides/manage-uploads#resuming
     *
     * @return array{complete: bool, drive_file_id: string|null, bytes_uploaded: int}
     */
    public function probeUploadStatus(string $sessionUri, int $totalSize): array
    {
        $token = $this->authService->getAccessToken();

        /**
         * When a browser aborts mid-chunk, Google may temporarily return 400
         * while finalizing the partial chunk. Retry up to 3 times with
         * increasing delays to allow Google to settle the session state.
         *
         * @see https://developers.google.com/workspace/drive/api/guides/manage-uploads#resuming
         */
        $response = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $response = Http::withToken($token['access_token'])
                ->withOptions(['allow_redirects' => false])
                ->withHeaders([
                    'Content-Range' => "bytes */{$totalSize}",
                ])
                ->send('PUT', $sessionUri);

            if ($response->status() !== 400 || $attempt === 3) {
                break;
            }

            sleep($attempt * 2);
        }

        $status = $response->status();

        if ($status === 200 || $status === 201) {
            return [
                'complete' => true,
                'drive_file_id' => $response->json('id'),
                'bytes_uploaded' => $totalSize,
            ];
        }

        if ($status === 308) {
            $range = $response->header('Range');
            $bytesUploaded = 0;

            if (preg_match('/bytes=\d+-(\d+)/', $range ?? '', $matches)) {
                $bytesUploaded = (int) $matches[1] + 1;
            }

            return [
                'complete' => false,
                'drive_file_id' => null,
                'bytes_uploaded' => $bytesUploaded,
            ];
        }

        if ($status === 404 || $status === 400) {
            throw new RuntimeException('La sesión de subida ha expirado. Debes iniciar la subida de nuevo.');
        }

        throw new RuntimeException("Error al verificar estado de subida: HTTP {$status}");
    }

    /**
     * Find an existing folder by name or create a new one.
     */
    private function findOrCreateFolder(Drive $drive, string $folderName, ?string $parentId = null): string
    {
        $query = "name = '{$folderName}' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";

        if ($parentId) {
            $query .= " and '{$parentId}' in parents";
        }

        $results = $drive->files->listFiles([
            'q' => $query,
            'fields' => 'files(id)',
            'pageSize' => 1,
        ]);

        $files = $results->getFiles();

        if (! empty($files)) {
            return $files[0]->getId();
        }

        $folderMetadata = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if ($parentId) {
            $folderMetadata->setParents([$parentId]);
        }

        $folder = $drive->files->create($folderMetadata, [
            'fields' => 'id',
        ]);

        return $folder->getId();
    }

    /**
     * Get storage usage for the Google Drive account.
     *
     * @return array{used_bytes: int, total_bytes: int, used_percent: float}
     */
    public function getStorageUsage(): array
    {
        $drive = $this->driveService();

        $about = $drive->about->get(['fields' => 'storageQuota']);
        $quota = $about->getStorageQuota();

        $used = (int) $quota->getUsage();
        $total = (int) $quota->getLimit();

        return [
            'used_bytes' => $used,
            'total_bytes' => $total,
            'used_percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }

    private function driveService(): Drive
    {
        return new Drive($this->authService->authenticatedClient());
    }
}
