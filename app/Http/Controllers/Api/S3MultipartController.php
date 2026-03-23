<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class S3MultipartController extends Controller
{
    private S3Client $client;

    private string $bucket;

    public function __construct()
    {
        /** @var array<string, mixed> $disk */
        $disk = config('filesystems.disks.s3');

        $this->bucket = $disk['bucket'];
        $this->client = app(S3Client::class);
    }

    /** Initiate a multipart upload. */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => 'required|string',
            'content_type' => 'required|string',
        ]);

        $key = 'uploads/'.Str::ulid().'/'.$request->input('filename');

        $result = $this->client->createMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $request->input('content_type'),
        ]);

        return response()->json([
            'uploadId' => $result['UploadId'],
            'key' => $key,
        ]);
    }

    /** Generate a presigned URL for uploading a single part. */
    public function signPart(Request $request, string $uploadId, int $partNumber): JsonResponse
    {
        $key = $this->requireKey($request);

        $command = $this->client->getCommand('UploadPart', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
        ]);

        $presignedUrl = (string) $this->client->createPresignedRequest($command, '+60 minutes')->getUri();

        return response()->json([
            'url' => $presignedUrl,
            'headers' => new \stdClass,
        ]);
    }

    /** List already-uploaded parts (for resume). */
    public function listParts(Request $request, string $uploadId): JsonResponse
    {
        $key = $this->requireKey($request);

        $parts = [];
        $isTruncated = true;
        $partNumberMarker = 0;

        while ($isTruncated) {
            $result = $this->client->listParts([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'UploadId' => $uploadId,
                'PartNumberMarker' => $partNumberMarker,
            ]);

            foreach ($result['Parts'] ?? [] as $part) {
                $parts[] = [
                    'PartNumber' => $part['PartNumber'],
                    'Size' => $part['Size'],
                    'ETag' => $part['ETag'],
                ];
            }

            $isTruncated = $result['IsTruncated'] ?? false;
            $partNumberMarker = $result['NextPartNumberMarker'] ?? 0;
        }

        return response()->json($parts);
    }

    /** Complete the multipart upload. */
    public function complete(Request $request, string $uploadId): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'parts' => 'required|array',
            'parts.*.PartNumber' => 'required|integer',
            'parts.*.ETag' => 'required|string',
        ]);

        $result = $this->client->completeMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $validated['key'],
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => $validated['parts'],
            ],
        ]);

        return response()->json([
            'location' => $result['Location'] ?? null,
        ]);
    }

    /** Abort a multipart upload and clean up parts. */
    public function abort(Request $request, string $uploadId): JsonResponse
    {
        $key = $this->requireKey($request);

        $this->client->abortMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);

        return response()->json(['ok' => true]);
    }

    private function requireKey(Request $request): string
    {
        $key = $request->input('key');

        abort_unless($key, 400, 'Missing key');

        return $key;
    }
}
