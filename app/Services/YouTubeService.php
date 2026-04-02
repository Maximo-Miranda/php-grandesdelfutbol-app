<?php

namespace App\Services;

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

    public function __construct(private GoogleAuthService $authService) {}

    /**
     * Upload a video to YouTube using resumable upload.
     *
     * @param  array<string>  $tags
     * @return string The YouTube video ID
     */
    public function uploadVideo(string $filePath, string $title, string $description, array $tags = []): string
    {
        $client = $this->authService->authenticatedClient();
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
        $client = $this->authService->authenticatedClient();
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
        $client = $this->authService->authenticatedClient();
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
        $client = $this->authService->authenticatedClient();
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
        return $this->authService->isConfigured();
    }
}
