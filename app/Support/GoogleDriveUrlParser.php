<?php

namespace App\Support;

class GoogleDriveUrlParser
{
    /**
     * Extract a Google Drive file ID from a shared URL.
     *
     * Supported formats (must be on drive.google.com or docs.google.com):
     * - https://drive.google.com/file/d/{FILEID}/view?usp=sharing
     * - https://drive.google.com/file/d/{FILEID}/view
     * - https://drive.google.com/file/d/{FILEID}/preview
     * - https://drive.google.com/open?id={FILEID}
     * - https://drive.google.com/uc?id={FILEID}
     * - https://docs.google.com/file/d/{FILEID}/...
     */
    public static function extractFileId(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === false) {
            return null;
        }

        if (! preg_match('/^(.+\.)?(drive|docs)\.google\.com$/', $host)) {
            return null;
        }

        if (preg_match('#/d/([a-zA-Z0-9_-]{10,})#', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('#[?&]id=([a-zA-Z0-9_-]{10,})#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
