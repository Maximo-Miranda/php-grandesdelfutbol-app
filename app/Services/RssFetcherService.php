<?php

namespace App\Services;

use App\Models\NewsSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RssFetcherService
{
    private const SNIPPET_MAX_LENGTH = 500;

    private const FULL_CONTENT_MAX_LENGTH = 20000;

    private const MAX_IMAGES_PER_ARTICLE = 10;

    /**
     * Fetch and normalize articles from an RSS/Atom source.
     *
     * @return Collection<int, array{
     *     external_id: string|null,
     *     title: string,
     *     snippet: string|null,
     *     full_content: string|null,
     *     image_url: string|null,
     *     image_urls: list<string>,
     *     original_url: string,
     *     author: string|null,
     *     published_at: string|null,
     * }>
     *
     * @throws RuntimeException When the feed cannot be fetched or parsed.
     */
    public function fetch(NewsSource $source): Collection
    {
        $response = Http::timeout(30)->get($source->url);

        if ($response->failed()) {
            throw new RuntimeException("HTTP {$response->status()} fetching RSS from {$source->name}");
        }

        $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);

        if ($xml === false) {
            throw new RuntimeException("Failed to parse XML from {$source->name}");
        }

        if (isset($xml->channel->item)) {
            return $this->parseRss($xml);
        }

        if (isset($xml->entry) || $xml->getName() === 'feed') {
            return $this->parseAtom($xml);
        }

        throw new RuntimeException("Unknown feed format from {$source->name}");
    }

    private function parseRss(\SimpleXMLElement $xml): Collection
    {
        $items = collect();

        foreach ($xml->channel->item as $item) {
            if ($entry = $this->normalizeRssEntry($item)) {
                $items->push($entry);
            }
        }

        return $items;
    }

    private function parseAtom(\SimpleXMLElement $xml): Collection
    {
        $items = collect();

        foreach ($xml->entry as $entry) {
            if ($normalized = $this->normalizeAtomEntry($entry)) {
                $items->push($normalized);
            }
        }

        return $items;
    }

    private function normalizeRssEntry(\SimpleXMLElement $item): ?array
    {
        $title = trim((string) $item->title);
        $link = trim((string) $item->link);

        if ($title === '' || ! $this->isSafeHttpUrl($link)) {
            return null;
        }

        $guid = trim((string) ($item->guid ?? $link));

        $content = $item->children('content', true);
        $contentEncoded = isset($content->encoded) ? trim((string) $content->encoded) : '';
        $descriptionRaw = trim((string) $item->description);

        $rawHtml = strlen($contentEncoded) > strlen($descriptionRaw) ? $contentEncoded : $descriptionRaw;

        $fullContent = $this->cleanHtmlToText($rawHtml, self::FULL_CONTENT_MAX_LENGTH);
        $snippet = $this->buildSnippet($fullContent);

        $author = $this->decodeText(trim((string) ($item->children('dc', true)->creator ?? $item->author ?? ''))) ?: null;
        $pubDate = trim((string) $item->pubDate) ?: null;

        $primaryImage = $this->extractImageFromRss($item);
        $imageUrls = array_slice(
            array_values(array_unique(array_filter([
                $primaryImage,
                ...$this->extractImagesFromHtml($rawHtml),
            ]))),
            0,
            self::MAX_IMAGES_PER_ARTICLE,
        );

        return [
            'external_id' => $guid,
            'title' => $this->decodeText($title),
            'snippet' => $snippet,
            'full_content' => $fullContent,
            'image_url' => $primaryImage ?? ($imageUrls[0] ?? null),
            'image_urls' => $imageUrls,
            'original_url' => $link,
            'author' => $author,
            'published_at' => $pubDate,
        ];
    }

    private function normalizeAtomEntry(\SimpleXMLElement $entry): ?array
    {
        $title = trim((string) $entry->title);

        $link = '';
        foreach ($entry->link as $linkEl) {
            $rel = (string) $linkEl['rel'];

            if ($rel === 'alternate' || $rel === '') {
                $link = (string) $linkEl['href'];
                break;
            }
        }

        if ($title === '' || ! $this->isSafeHttpUrl($link)) {
            return null;
        }

        $id = trim((string) ($entry->id ?? $link));
        $rawHtml = trim((string) ($entry->content ?? $entry->summary ?? ''));

        $fullContent = $this->cleanHtmlToText($rawHtml, self::FULL_CONTENT_MAX_LENGTH);
        $snippet = $this->buildSnippet($fullContent);

        $author = $this->decodeText(trim((string) ($entry->author->name ?? ''))) ?: null;
        $pubDate = trim((string) ($entry->published ?? $entry->updated ?? '')) ?: null;

        $contentImages = array_slice($this->extractImagesFromHtml($rawHtml), 0, self::MAX_IMAGES_PER_ARTICLE);

        return [
            'external_id' => $id,
            'title' => $this->decodeText($title),
            'snippet' => $snippet,
            'full_content' => $fullContent,
            'image_url' => $contentImages[0] ?? null,
            'image_urls' => $contentImages,
            'original_url' => $link,
            'author' => $author,
            'published_at' => $pubDate,
        ];
    }

    private function extractImageFromRss(\SimpleXMLElement $item): ?string
    {
        $media = $item->children('media', true);

        if (isset($media->thumbnail)) {
            $url = (string) $media->thumbnail->attributes()->url;

            if ($url !== '' && $this->isImageUrl($url)) {
                return $url;
            }
        }

        if (isset($media->content)) {
            $type = (string) $media->content->attributes()->type;
            $url = (string) $media->content->attributes()->url;

            if ($url !== '' && (str_starts_with($type, 'image/') || ($type === '' && $this->isImageUrl($url)))) {
                return $url;
            }
        }

        if (isset($item->enclosure)) {
            $type = (string) $item->enclosure['type'];
            $url = (string) $item->enclosure['url'];

            if (str_starts_with($type, 'image/') && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * Extract <img src="..."> URLs from raw HTML content.
     *
     * @return list<string>
     */
    private function extractImagesFromHtml(string $html): array
    {
        if ($html === '' || ! str_contains($html, '<img')) {
            return [];
        }

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches);

        return array_values(array_unique(array_filter(
            $matches[1] ?? [],
            fn (string $url): bool => $this->isImageUrl($url),
        )));
    }

    private function isImageUrl(string $url): bool
    {
        if (! $this->isSafeHttpUrl($url)) {
            return false;
        }

        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return ! str($path)->endsWith(['.mp4', '.webm', '.avi', '.mov', '.m3u8', '.flv']);
    }

    /**
     * Whitelist http/https URLs only. Rejects javascript:, data:, file:, etc. so
     * untrusted feed content cannot inject dangerous URLs into the database.
     */
    private function isSafeHttpUrl(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > 2048) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'http' || $scheme === 'https';
    }

    /**
     * Convert HTML to plain text while preserving paragraph breaks so the
     * resulting content is readable when rendered with `whitespace-pre-line`.
     */
    private function cleanHtmlToText(string $html, int $maxLength): ?string
    {
        if ($html === '') {
            return null;
        }

        $html = preg_replace('/<blockquote[^>]*class="[^"]*twitter-tweet[^"]*"[\s\S]*?<\/blockquote>/iu', '', $html);
        $html = preg_replace('/<(script|iframe|figure|aside)[^>]*>[\s\S]*?<\/\1>/iu', '', $html);
        $html = preg_replace(
            '/<a[^>]*>\s*(Leer[^<]*|Seguir leyendo[^<]*|Read more[^<]*|Ver m[aá]s[^<]*)<\/a>/iu',
            '',
            $html,
        );

        // Mark block boundaries before stripping tags so paragraphs survive strip_tags().
        $html = preg_replace('/<br\s*\/?\s*>/i', "\n", $html);
        $html = preg_replace('/<\/(p|div|li|h[1-6]|blockquote)>/i', "\n\n", $html);
        $html = preg_replace('/<(p|div|li|h[1-6]|blockquote)[^>]*>/i', '', $html);

        $text = $this->decodeText(strip_tags($html));

        $text = preg_replace([
            '#https?://\S+#',
            '#pic\.twitter\.com/\S+#i',
            '#(?:www\.)?\w+\.(?:com|net|org|co|es|mx|ar|cl|pe)/\S*#i',
            '/[—–-]\s*[\w ]+\s*\(@\w+\)\s*[A-Za-záéíóúÁÉÍÓÚ]+\s+\d{1,2},?\s+\d{4}/u',
            '/@\w+/',
        ], '', $text);

        $trailingReadMore = '/\s*[»›→.…\s]*(Leer(\s+m[aá]s)?|Seguir leyendo|Read more|Ver m[aá]s)\s*[»›→.…]*\s*$/iu';

        do {
            $previous = $text;
            $text = preg_replace($trailingReadMore, '', $text);
        } while ($text !== $previous);

        // Collapse horizontal whitespace but keep \n\n paragraph breaks for the UI.
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/ ?\n ?/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text !== '' ? mb_substr($text, 0, $maxLength) : null;
    }

    private function decodeText(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/[\x{00A0}\x{200B}\x{FEFF}]/u', ' ', $decoded) ?? $decoded;
    }

    /**
     * Build a single-line snippet from the cleaned full content.
     */
    private function buildSnippet(?string $fullContent): ?string
    {
        if ($fullContent === null || $fullContent === '') {
            return null;
        }

        $singleLine = preg_replace('/\s+/u', ' ', $fullContent);

        return $this->truncateAtWord(trim($singleLine), self::SNIPPET_MAX_LENGTH);
    }

    /**
     * Truncate at a word boundary without cutting mid-word.
     */
    private function truncateAtWord(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace > ($maxLength * 0.7)) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, ' .,;:').'…';
    }
}
