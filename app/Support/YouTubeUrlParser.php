<?php

namespace App\Support;

class YouTubeUrlParser
{
    /**
     * Extract a YouTube video ID from a URL or raw ID string.
     *
     * Supported formats:
     * - https://www.youtube.com/watch?v=VIDEO_ID
     * - https://youtube.com/watch?v=VIDEO_ID
     * - https://youtu.be/VIDEO_ID
     * - https://www.youtube.com/embed/VIDEO_ID
     * - Raw 11-character video ID (alphanumeric, hyphens, underscores)
     */
    public static function extractVideoId(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // Raw 11-character video ID
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
            return $url;
        }

        // youtube.com/watch?v=VIDEO_ID
        if (preg_match('/(?:youtube\.com\/watch\?.*v=)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return $matches[1];
        }

        // youtu.be/VIDEO_ID
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return $matches[1];
        }

        // youtube.com/embed/VIDEO_ID
        if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
