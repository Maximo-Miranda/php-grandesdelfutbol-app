<?php

namespace App\Services;

use App\Models\NewsSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RssFetcherService
{
    /**
     * Fetch and normalize articles from an RSS/Atom source.
     *
     * @return Collection<int, array{
     *     external_id: string|null,
     *     title: string,
     *     snippet: string|null,
     *     image_url: string|null,
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

        $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);

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
            $entry = $this->normalizeRssEntry($item);

            if ($entry !== null) {
                $items->push($entry);
            }
        }

        return $items;
    }

    private function parseAtom(\SimpleXMLElement $xml): Collection
    {
        $items = collect();

        foreach ($xml->entry as $entry) {
            $normalized = $this->normalizeAtomEntry($entry);

            if ($normalized !== null) {
                $items->push($normalized);
            }
        }

        return $items;
    }

    private function normalizeRssEntry(\SimpleXMLElement $item): ?array
    {
        $title = trim((string) $item->title);
        $link = trim((string) $item->link);

        if ($title === '' || $link === '') {
            return null;
        }

        $guid = trim((string) ($item->guid ?? $link));
        $description = strip_tags(trim((string) $item->description));
        $snippet = mb_substr($description, 0, 500) ?: null;
        $author = trim((string) ($item->children('dc', true)->creator ?? $item->author ?? '')) ?: null;
        $pubDate = trim((string) $item->pubDate) ?: null;

        $imageUrl = $this->extractImageFromRss($item);

        return [
            'external_id' => $guid,
            'title' => $title,
            'snippet' => $snippet,
            'image_url' => $imageUrl,
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

        if ($title === '' || $link === '') {
            return null;
        }

        $id = trim((string) ($entry->id ?? $link));
        $summary = strip_tags(trim((string) ($entry->summary ?? $entry->content ?? '')));
        $snippet = mb_substr($summary, 0, 500) ?: null;
        $author = trim((string) ($entry->author->name ?? '')) ?: null;
        $pubDate = trim((string) ($entry->published ?? $entry->updated ?? '')) ?: null;

        return [
            'external_id' => $id,
            'title' => $title,
            'snippet' => $snippet,
            'image_url' => null,
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

    private function isImageUrl(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return ! str($path)->endsWith(['.mp4', '.webm', '.avi', '.mov', '.m3u8', '.flv']);
    }
}
