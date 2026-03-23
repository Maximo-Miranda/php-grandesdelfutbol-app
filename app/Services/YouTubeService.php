<?php

namespace App\Services;

use App\Models\YouTubeToken;
use Google\Client as GoogleClient;
use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\Playlist;
use Google\Service\YouTube\PlaylistItem;
use Google\Service\YouTube\PlaylistItemSnippet;
use Google\Service\YouTube\PlaylistSnippet;
use Google\Service\YouTube\PlaylistStatus;
use Google\Service\YouTube\ResourceId;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class YouTubeService
{
    private const int CHUNK_SIZE = 16 * 1024 * 1024;

    /** Get the Google OAuth authorization URL. */
    public function getAuthUrl(): string
    {
        $client = $this->baseClient();
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope(YouTube::YOUTUBE);

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

        /** @var RequestInterface $insertRequest */
        $insertRequest = $youtube->videos->insert('snippet,status', $video);

        $media = new MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            null,
            true,
            self::CHUNK_SIZE,
        );

        $media->setFileSize(filesize($filePath));

        $handle = fopen($filePath, 'rb');
        $uploadStatus = false;

        while (! $uploadStatus && ! feof($handle)) {
            $chunk = fread($handle, self::CHUNK_SIZE);
            $uploadStatus = $media->nextChunk($chunk);
        }

        fclose($handle);
        $client->setDefer(false);

        if (! $uploadStatus instanceof Video) {
            throw new RuntimeException('YouTube upload failed: no video returned.');
        }

        return $uploadStatus->getId();
    }

    /**
     * Check the processing status of a YouTube video.
     *
     * @return string 'processing', 'succeeded', 'failed', 'terminated', or 'unknown'
     */
    public function getProcessingStatus(string $videoId): string
    {
        $client = $this->client();
        $youtube = new YouTube($client);

        $response = $youtube->videos->listVideos('processingDetails', ['id' => $videoId]);

        $items = $response->getItems();

        if (empty($items)) {
            return 'unknown';
        }

        $processingDetails = $items[0]->getProcessingDetails();

        if (! $processingDetails) {
            return 'unknown';
        }

        return $processingDetails->getProcessingStatus() ?? 'unknown';
    }

    /**
     * Create a YouTube playlist.
     *
     * @return string The playlist ID
     */
    public function createPlaylist(string $title, string $description = ''): string
    {
        $client = $this->client();
        $youtube = new YouTube($client);

        $snippet = new PlaylistSnippet;
        $snippet->setTitle(mb_substr($title, 0, 150));
        $snippet->setDescription(mb_substr($description, 0, 5000));

        $status = new PlaylistStatus;
        $status->setPrivacyStatus('public');

        $playlist = new Playlist;
        $playlist->setSnippet($snippet);
        $playlist->setStatus($status);

        $response = $youtube->playlists->insert('snippet,status', $playlist);

        return $response->getId();
    }

    /** Add a video to a YouTube playlist. */
    public function addToPlaylist(string $playlistId, string $videoId): void
    {
        $client = $this->client();
        $youtube = new YouTube($client);

        $snippet = new PlaylistItemSnippet;
        $snippet->setPlaylistId($playlistId);
        $snippet->setResourceId(new ResourceId([
            'kind' => 'youtube#video',
            'videoId' => $videoId,
        ]));

        $item = new PlaylistItem;
        $item->setSnippet($snippet);

        $youtube->playlistItems->insert('snippet', $item);
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
