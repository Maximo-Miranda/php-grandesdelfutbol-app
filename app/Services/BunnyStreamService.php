<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BunnyStreamService
{
    private const string BASE_URL = 'https://video.bunnycdn.com';

    public function __construct(
        private string $libraryId = '',
        private string $apiKey = '',
        private string $cdnHostname = '',
    ) {
        $this->libraryId = config('bunny.stream_library_id');
        $this->apiKey = config('bunny.stream_api_key');
        $this->cdnHostname = config('bunny.cdn_hostname');
    }

    /**
     * Create a new video in Bunny Stream.
     *
     * @return array{guid: string, ...}
     */
    public function createVideo(string $title): array
    {
        return $this->client()
            ->post($this->videoUrl(), ['title' => $title])
            ->throw()
            ->json();
    }

    /**
     * Get video details/status from Bunny Stream.
     *
     * @return array{guid: string, status: int, length: int, ...}
     */
    public function getVideo(string $videoId): array
    {
        return $this->client()
            ->get($this->videoUrl($videoId))
            ->throw()
            ->json();
    }

    /** Delete a video from Bunny Stream. */
    public function deleteVideo(string $videoId): void
    {
        $this->client()
            ->delete($this->videoUrl($videoId))
            ->throw();
    }

    /**
     * Generate TUS upload credentials for direct browser upload.
     *
     * @return array{upload_url: string, video_id: string, auth_signature: string, auth_expire: int, library_id: string}
     */
    public function getTusUploadUrl(string $videoId, int $expirationSeconds = 86400): array
    {
        $expiration = time() + $expirationSeconds;
        $signature = hash('sha256', $this->libraryId.$this->apiKey.$expiration.$videoId);

        return [
            'upload_url' => self::BASE_URL.'/tusupload',
            'video_id' => $videoId,
            'auth_signature' => $signature,
            'auth_expire' => $expiration,
            'library_id' => $this->libraryId,
        ];
    }

    /** Download the video MP4 to a local file (for reel generation). Uses curl to avoid memory issues with large files. */
    public function downloadVideo(string $videoId, string $outputPath): void
    {
        $url = "https://{$this->cdnHostname}/{$videoId}/play_720p.mp4";

        $fp = fopen($outputPath, 'wb');
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_REFERER => 'https://grandesdelfutbol.com',
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fp);

        if (! $success || $httpCode !== 200) {
            @unlink($outputPath);

            throw new \RuntimeException("Error al descargar video de Bunny: {$httpCode}");
        }
    }

    /** HLS streaming URL for the video player. */
    public function getStreamUrl(string $videoId): string
    {
        return "https://{$this->cdnHostname}/{$videoId}/playlist.m3u8";
    }

    private function videoUrl(?string $videoId = null): string
    {
        $url = self::BASE_URL."/library/{$this->libraryId}/videos";

        return $videoId ? "{$url}/{$videoId}" : $url;
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'AccessKey' => $this->apiKey,
            'Accept' => 'application/json',
        ])->timeout(30);
    }
}
