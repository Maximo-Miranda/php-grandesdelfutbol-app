<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\NewsArticle;
use App\Models\Team;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $base = rtrim((string) config('app.url'), '/');

        $urls = [
            ['loc' => $base.'/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $base.'/news', 'changefreq' => 'hourly', 'priority' => '0.9'],
            ['loc' => $base.'/terms', 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => $base.'/privacy', 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        Club::query()
            ->where('is_public', true)
            ->whereNotNull('slug')
            ->orderBy('id')
            ->limit(5000)
            ->get(['slug', 'updated_at'])
            ->each(function (Club $club) use (&$urls, $base): void {
                $urls[] = [
                    'loc' => $base.'/club/'.$club->slug,
                    'lastmod' => $club->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            });

        Team::query()
            ->whereHas('club', fn ($q) => $q->where('is_public', true))
            ->orderBy('id')
            ->limit(5000)
            ->get(['ulid', 'updated_at'])
            ->each(function (Team $team) use (&$urls, $base): void {
                $urls[] = [
                    'loc' => $base.'/team/'.$team->ulid,
                    'lastmod' => $team->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                ];
            });

        NewsArticle::query()
            ->whereNotNull('slug')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit(5000)
            ->get(['slug', 'published_at', 'updated_at'])
            ->each(function (NewsArticle $article) use (&$urls, $base): void {
                $urls[] = [
                    'loc' => $base.'/news/'.$article->slug,
                    'lastmod' => ($article->updated_at ?? $article->published_at)?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                ];
            });

        $xml = $this->render($urls);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @param  array<int, array<string, string|null>>  $urls
     */
    private function render(array $urls): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>'."\n";

            if (! empty($url['lastmod'])) {
                $xml .= '    <lastmod>'.$url['lastmod'].'</lastmod>'."\n";
            }

            if (! empty($url['changefreq'])) {
                $xml .= '    <changefreq>'.$url['changefreq'].'</changefreq>'."\n";
            }

            if (! empty($url['priority'])) {
                $xml .= '    <priority>'.$url['priority'].'</priority>'."\n";
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
