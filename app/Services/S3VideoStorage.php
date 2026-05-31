<?php

namespace App\Services;

use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use RuntimeException;
use Throwable;

class S3VideoStorage
{
    public function __construct(private S3Client $client) {}

    /**
     * Upload a local file to S3 using a multipart upload.
     *
     * Streams the file in parts so memory stays constant regardless of file
     * size — required for multi-GB match videos that would otherwise exhaust
     * the worker's memory when buffered by the default Flysystem put().
     */
    public function putFile(string $localPath, string $s3Key): void
    {
        $bucket = config('filesystems.disks.s3.bucket');

        $uploader = new MultipartUploader($this->client, $localPath, [
            'bucket' => $bucket,
            'key' => $s3Key,
            'part_size' => (int) config('youtube.drive.s3_part_size_bytes', 64 * 1024 * 1024),
        ]);

        try {
            $uploader->upload();
        } catch (Throwable $e) {
            throw new RuntimeException("No se pudo subir el video a S3 ({$s3Key}): {$e->getMessage()}", 0, $e);
        }
    }
}
