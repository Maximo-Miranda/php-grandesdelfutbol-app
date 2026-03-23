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

    /** Check if encoding is fully complete via the API (encodeProgress === 100). */
    public function isFullyEncoded(string $videoId): bool
    {
        $video = $this->getVideo($videoId);

        return ($video['encodeProgress'] ?? 0) === 100;
    }

    /**
     * Get available resolutions for a video, ordered from highest to lowest.
     *
     * @return list<string>
     */
    public function getAvailableResolutions(string $videoId): array
    {
        $video = $this->getVideo($videoId);
        $resolutions = $video['availableResolutions'] ?? '';

        if (! is_string($resolutions) || $resolutions === '') {
            return [];
        }

        $parsed = array_map('trim', explode(',', $resolutions));

        usort($parsed, fn (string $a, string $b) => (int) $b <=> (int) $a);

        return $parsed;
    }

    /**
     * Download the highest available resolution from Bunny.
     *
     * @return string The resolution that was downloaded (e.g. '1080p')
     */
    public function downloadHighestQuality(string $videoId, string $outputPath): string
    {
        $resolutions = $this->getAvailableResolutions($videoId);

        if ($resolutions === []) {
            throw new \RuntimeException('No hay resoluciones disponibles para descargar.');
        }

        foreach ($resolutions as $resolution) {
            $url = "https://{$this->cdnHostname}/{$videoId}/play_{$resolution}.mp4";

            try {
                $this->downloadFromCdn($url, $outputPath);

                return $resolution;
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), '404')) {
                    continue;
                }
                throw $e;
            }
        }

        throw new \RuntimeException('No se pudo descargar ninguna resolución disponible.');
    }

    /** Download a specific resolution MP4 to a local file. Uses curl with bandwidth throttle. */
    public function downloadVideo(string $videoId, string $outputPath, ?string $resolution = '720p'): void
    {
        $url = "https://{$this->cdnHostname}/{$videoId}/play_{$resolution}.mp4";

        $this->downloadFromCdn($url, $outputPath);
    }

    /** HLS streaming URL for the video player. */
    public function getStreamUrl(string $videoId): string
    {
        return "https://{$this->cdnHostname}/{$videoId}/playlist.m3u8";
    }

    /** Download a file from the CDN with bandwidth throttling. */
    private function downloadFromCdn(string $url, string $outputPath): void
    {
        $fp = fopen($outputPath, 'wb');
        $ch = curl_init($url);

        $speedLimit = (int) config('bunny.download_speed_limit', 0);

        $options = [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 1800,
            CURLOPT_REFERER => 'https://grandesdelfutbol.com',
        ];

        if ($speedLimit > 0) {
            $options[CURLOPT_MAX_RECV_SPEED_LARGE] = $speedLimit;
        }

        curl_setopt_array($ch, $options);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (! $success || $httpCode !== 200) {
            @unlink($outputPath);

            throw new \RuntimeException("Error al descargar video de Bunny: {$httpCode}");
        }
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
