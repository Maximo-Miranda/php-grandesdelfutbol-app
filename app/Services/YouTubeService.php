<?php

namespace App\Services;

use App\Models\YouTubeToken;
use Google\Client as GoogleClient;
use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use GuzzleHttp\Psr7\Request;
use RuntimeException;

class YouTubeService
{
    /** Get the Google OAuth authorization URL. */
    public function getAuthUrl(): string
    {
        $client = $this->baseClient();
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope(YouTube::YOUTUBE_UPLOAD);

        return $client->createAuthUrl();
    }

    /** Exchange an authorization code for tokens and store them. */
    public function handleCallback(string $code): void
    {
        $client = $this->baseClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException("YouTube OAuth error: {$token['error_description']}");
        }

        YouTubeToken::query()->delete();
        YouTubeToken::create(['token' => $token]);
    }

    /**
     * Upload a video to YouTube using resumable upload.
     *
     * @param  array<string>  $tags
     * @return string The YouTube video ID
     */
    public function uploadVideo(string $filePath, string $title, string $description, array $tags = []): string
    {
        $client = $this->client();
        $client->setDefer(true);

        $youtube = new YouTube($client);

        $snippet = new VideoSnippet;
        $snippet->setTitle(mb_substr($title, 0, 100));
        $snippet->setDescription(mb_substr($description, 0, 5000));
        $snippet->setTags(array_slice($tags, 0, 30));
        $snippet->setCategoryId(config('youtube.category_id'));

        $status = new VideoStatus;
        $status->setPrivacyStatus(config('youtube.default_privacy'));

        $video = new Video;
        $video->setSnippet($snippet);
        $video->setStatus($status);

        /** @var Request $insertRequest */
        $insertRequest = $youtube->videos->insert('snippet,status', $video);

        $media = new MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            null,
            true,
            16 * 1024 * 1024, // 16 MB chunks
        );

        $fileSize = filesize($filePath);
        $media->setFileSize($fileSize);

        $handle = fopen($filePath, 'rb');
        $uploadStatus = false;

        while (! $uploadStatus && ! feof($handle)) {
            $chunk = fread($handle, 16 * 1024 * 1024);
            $uploadStatus = $media->nextChunk($chunk);
        }

        fclose($handle);
        $client->setDefer(false);

        if (! $uploadStatus instanceof Video) {
            throw new RuntimeException('YouTube upload failed: no video returned.');
        }

        return $uploadStatus->getId();
    }

    /** Check if YouTube is configured with a valid token. */
    public function isConfigured(): bool
    {
        return YouTubeToken::current() !== null;
    }

    /** Build an authenticated Google Client with auto-refresh. */
    private function client(): GoogleClient
    {
        $client = $this->baseClient();

        $tokenRecord = YouTubeToken::current();

        if (! $tokenRecord) {
            throw new RuntimeException('YouTube no está configurado. Autoriza la cuenta en /admin/youtube/authorize.');
        }

        $client->setAccessToken($tokenRecord->token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();

            if (! $refreshToken) {
                throw new RuntimeException('YouTube refresh token no disponible. Re-autoriza la cuenta.');
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newToken['error'])) {
                throw new RuntimeException("YouTube token refresh failed: {$newToken['error_description']}");
            }

            $tokenRecord->update(['token' => $client->getAccessToken()]);
        }

        return $client;
    }

    /** Build a base Google Client without tokens. */
    private function baseClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId(config('youtube.client_id'));
        $client->setClientSecret(config('youtube.client_secret'));
        $client->setRedirectUri(config('youtube.redirect_uri'));

        return $client;
    }
}
