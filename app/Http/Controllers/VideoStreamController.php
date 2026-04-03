<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\FootballMatch;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoStreamController extends Controller
{
    public function __construct(private GoogleDriveService $driveService) {}

    public function stream(Request $request, Club $club, FootballMatch $match): StreamedResponse
    {
        Gate::authorize('update', $match);

        $videoUpload = $match->videoUpload;

        abort_unless($videoUpload?->drive_file_id, 404);

        $metadata = $this->driveService->getFileMetadata($videoUpload->drive_file_id);
        $fileSize = $metadata['size'];
        $mimeType = $metadata['mimeType'] ?: 'video/mp4';

        $start = 0;
        $end = $fileSize - 1;
        $status = 200;
        $headers = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-cache',
        ];

        $rangeHeader = $request->header('Range');

        if ($rangeHeader && preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
            $start = (int) $matches[1];
            $end = ! empty($matches[2]) ? (int) $matches[2] : $fileSize - 1;
            $status = 206;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
        }

        $headers['Content-Length'] = $end - $start + 1;

        return response()->stream(function () use ($videoUpload, $start, $end) {
            $content = $this->driveService->streamFileContent($videoUpload->drive_file_id);

            $bytesRead = 0;
            $bytesToSkip = $start;
            $bytesRemaining = $end - $start + 1;

            while (! $content->eof() && $bytesRemaining > 0) {
                $chunk = $content->read(8192);
                $chunkLen = strlen($chunk);

                if ($bytesToSkip > 0) {
                    if ($bytesToSkip >= $chunkLen) {
                        $bytesToSkip -= $chunkLen;

                        continue;
                    }
                    $chunk = substr($chunk, $bytesToSkip);
                    $chunkLen = strlen($chunk);
                    $bytesToSkip = 0;
                }

                if ($chunkLen > $bytesRemaining) {
                    $chunk = substr($chunk, 0, $bytesRemaining);
                    $chunkLen = $bytesRemaining;
                }

                echo $chunk;
                flush();
                $bytesRemaining -= $chunkLen;
            }
        }, $status, $headers);
    }
}
