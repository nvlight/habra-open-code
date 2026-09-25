<?php

namespace App\Services;

use App\Models\HabrSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class HabrSitemapService
{
    public const DEFAULT_SINCE_DATE = '2026-01-01';

    private const HARD_FLOOR_SOURCE_ID = 900000;

    private const SITEMAP_INDEX_URL = 'https://habr.com/sitemap.xml';

    public function discover(string $sinceDate): array
    {
        $created = 0;
        $updated = 0;

        $sitemapUrls = $this->fetchSitemapIndex();

        foreach ($sitemapUrls as $sitemapUrl) {
            $parsed = $this->parseSitemap($sitemapUrl, $sinceDate);

            foreach ($parsed as $entry) {
                $source = HabrSource::query()->firstOrNew(['source_id' => $entry['source_id']]);

                if (! $source->exists) {
                    $source->fill([
                        'status' => HabrSource::STATUS_PENDING,
                        'attempts' => 0,
                        ...$entry,
                    ])->save();
                    $created++;

                    continue;
                }

                $changed = $source->url !== $entry['url']
                    || $source->type !== $entry['type']
                    || $source->company_slug !== $entry['company_slug']
                    || ! Carbon::parse($entry['lastmod'])->equalTo($source->lastmod);

                if ($changed) {
                    $source->forceFill($entry)->save();
                    $updated++;
                }
            }
        }

        Log::info("Habr sitemap discovery finished: {$created} created, {$updated} updated");

        return compact('created', 'updated');
    }

    /**
     * @return list<string>
     */
    private function fetchSitemapIndex(): array
    {
        $response = Http::accept('application/xml')
            ->timeout(30)
            ->get(self::SITEMAP_INDEX_URL);

        $response->throw();

        $xml = $this->loadXml($response->body());
        $urls = [];

        /** @var SimpleXMLElement $sitemap */
        foreach ($xml->sitemap as $sitemap) {
            $loc = (string) $sitemap->loc;

            if (! $this->isContentSitemap($loc)) {
                continue;
            }

            $urls[] = $loc;
        }

        return $urls;
    }

    private function isContentSitemap(string $url): bool
    {
        return (bool) preg_match('#sitemap_(articles|news|posts|specials)\d+\.xml$#', $url);
    }

    /**
     * @return list<array{source_id: int, url: string, type: string, company_slug: string|null, lastmod: string}>
     */
    private function parseSitemap(string $sitemapUrl, string $sinceDate): array
    {
        $response = Http::accept('application/xml')
            ->timeout(60)
            ->get($sitemapUrl);

        $response->throw();

        $xml = $this->loadXml($response->body());
        $entries = [];

        /** @var SimpleXMLElement $urlNode */
        foreach ($xml->url as $urlNode) {
            $loc = (string) $urlNode->loc;
            $lastmod = (string) $urlNode->lastmod;

            if ($lastmod < $sinceDate) {
                continue;
            }

            $parsed = $this->parseUrl($loc);

            if ($parsed === null) {
                continue;
            }

            // Старые статьи, "потревоженные" правками в 2026, не нужны архиву:
            // их lastmod >= since, но публикация была задолго до 2026-01-01.
            if ($parsed['source_id'] < self::HARD_FLOOR_SOURCE_ID) {
                continue;
            }

            $entries[] = [...$parsed, 'lastmod' => $lastmod];
        }

        return $entries;
    }

    /**
     * @return array{source_id: int, url: string, type: string, company_slug: string|null}|null
     */
    public function parseUrl(string $url): ?array
    {
        $map = [
            'articles' => HabrSource::TYPE_ARTICLE,
            'news' => HabrSource::TYPE_NEWS,
            'posts' => HabrSource::TYPE_POST,
            'specials' => HabrSource::TYPE_SPECIAL,
        ];

        $patterns = [
            '#^https://habr\.com/ru/(articles|news|posts|specials)/(\d+)/$#' => 'plain',
            '#^https://habr\.com/ru/companies/([^/]+)/(articles|news|posts)/(\d+)/$#' => 'company',
        ];

        foreach ($patterns as $pattern => $kind) {
            if (! preg_match($pattern, $url, $m)) {
                continue;
            }

            if ($kind === 'plain') {
                return [
                    'source_id' => (int) $m[2],
                    'url' => $url,
                    'type' => $map[$m[1]],
                    'company_slug' => null,
                ];
            }

            return [
                'source_id' => (int) $m[3],
                'url' => $url,
                'type' => $map[$m[2]],
                'company_slug' => $m[1],
            ];
        }

        return null;
    }

    private function loadXml(string $body): SimpleXMLElement
    {
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET);

        if ($xml === false) {
            throw new \RuntimeException('Failed to parse XML from habr sitemap');
        }

        return $xml;
    }
}
