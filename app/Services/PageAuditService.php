<?php

namespace App\Services;

use App\Models\PageAuditReport;
use App\Models\SearchConsoleData;
use App\Support\Audit\HtmlAuditor;
use App\Support\Audit\KeywordStrategyAnalyzer;
use App\Support\Audit\RecommendationEngine;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PageAuditService
{
    private const MAX_LINKS_CHECKED = 100;

    private const LINK_TIMEOUT = 8;

    private const LINK_POOL_CONCURRENCY = 10;

    public function audit(int $websiteId, string $pageUrl): PageAuditReport
    {
        $startedAt = microtime(true);

        try {
            $fetch = $this->fetch($pageUrl);

            if (! isset($fetch['body'])) {
                return $this->persistFailure($websiteId, $pageUrl, $fetch['error'] ?? 'Failed to fetch page', $fetch['http_status'] ?? null, $fetch['ttfb_ms'] ?? null);
            }

            $html = $fetch['body'];
            $auditor = new HtmlAuditor($html, $pageUrl);

            $metadata = $auditor->metadata();
            $headings = $auditor->headings();
            $content = $auditor->content();
            $bodyText = $content['body_text'];
            unset($content['body_text']);
            $images = $auditor->images();
            $links = $auditor->links();
            $schema = $auditor->schema();
            $favicon = $auditor->favicon();
            $readability = $auditor->readability($bodyText);

            $allLinks = array_merge($links['internal'], $links['external']);
            $linkCheck = $this->checkLinks($allLinks);
            $links['broken'] = $linkCheck['broken'];
            $links['links_checked'] = $linkCheck['checked'];
            $links['links_skipped'] = $linkCheck['skipped'];

            $technical = [
                'http_status' => $fetch['http_status'],
                'ttfb_ms' => $fetch['ttfb_ms'],
                'page_size_bytes' => $fetch['size'],
                'compression' => $fetch['compression'],
                'is_https' => str_starts_with(strtolower($pageUrl), 'https://'),
            ];

            $result = [
                'metadata' => $metadata,
                'content' => $content + ['headings' => $headings['headings'], 'h1_count' => $headings['h1_count'], 'heading_order_ok' => $headings['heading_order_ok']],
                'images' => $images,
                'links' => $links,
                'technical' => $technical,
                'advanced' => [
                    'schema_blocks' => $schema['count'],
                    'schema_blocks_detail' => $schema['blocks'],
                    'readability' => $readability,
                    'has_favicon' => $favicon['present'],
                    'favicon_href' => $favicon['href'],
                ],
            ];

            $targetKeywords = $this->fetchTargetKeywords($websiteId, $pageUrl);
            $h1Texts = array_values(array_filter(array_map(
                fn ($h) => $h['level'] === 1 ? ($h['text'] ?? '') : null,
                $headings['headings'] ?? []
            )));
            $allHeadingsText = implode(' ', array_map(fn ($h) => $h['text'] ?? '', $headings['headings'] ?? []));

            $result['keywords'] = app(KeywordStrategyAnalyzer::class)->analyze($targetKeywords, [
                'title' => $metadata['title'] ?? '',
                'meta_description' => $metadata['meta_description'] ?? '',
                'h1_text' => implode(' ', $h1Texts),
                'all_headings_text' => $allHeadingsText,
                'body_text' => $bodyText,
                'keyword_density' => $content['keyword_density'] ?? [],
            ]);

            $benchmark = $this->buildSerperReadabilityBenchmark(
                $pageUrl,
                $result['keywords'],
                is_numeric($readability['flesch'] ?? null) ? (float) $readability['flesch'] : null
            );
            if ($benchmark !== null) {
                $result['benchmark'] = $benchmark;
            }

            $result['recommendations'] = app(RecommendationEngine::class)->analyze($result);

            return PageAuditReport::updateOrCreate(
                ['website_id' => $websiteId, 'page' => $pageUrl],
                [
                    'status' => 'completed',
                    'audited_at' => now(),
                    'http_status' => $fetch['http_status'],
                    'response_time_ms' => $fetch['ttfb_ms'],
                    'page_size_bytes' => $fetch['size'],
                    'error_message' => null,
                    'result' => $result,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("PageAuditService failed for website {$websiteId} page {$pageUrl}: {$e->getMessage()}");

            return $this->persistFailure($websiteId, $pageUrl, $e->getMessage(), null, (int) round((microtime(true) - $startedAt) * 1000));
        }
    }

    /**
     * Optional SERP readability benchmark via Serper (fail-closed: never throws).
     *
     * @param  array<string, mixed>  $keywordsPayload
     * @return array<string, mixed>|null null when Serper is not configured
     */
    private function buildSerperReadabilityBenchmark(string $pageUrl, array $keywordsPayload, ?float $yourFlesch): ?array
    {
        $apiKey = config('services.serper.key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        $keywordContext = null;

        try {
            if (empty($keywordsPayload['available']) || empty($keywordsPayload['primary']['query'])) {
                return [
                    'keyword' => null,
                    'source' => 'serper',
                    'your_flesch' => $yourFlesch,
                    'competitors' => [],
                    'skipped_reason' => 'no_primary_keyword',
                ];
            }

            $keyword = trim((string) $keywordsPayload['primary']['query']);
            $keywordContext = $keyword !== '' ? $keyword : null;
            if ($keyword === '') {
                return [
                    'keyword' => null,
                    'source' => 'serper',
                    'your_flesch' => $yourFlesch,
                    'competitors' => [],
                    'skipped_reason' => 'no_primary_keyword',
                ];
            }

            $payload = app(SerperSearchClient::class)->search($keyword, 20);
            if ($payload === null) {
                return [
                    'keyword' => $keyword,
                    'source' => 'serper',
                    'your_flesch' => $yourFlesch,
                    'competitors' => [],
                    'skipped_reason' => 'serper_request_failed',
                    'your_serp' => $this->emptyYourSerpSnapshot(),
                ];
            }

            $organic = $payload['organic'] ?? [];
            if (! is_array($organic) || $organic === []) {
                return [
                    'keyword' => $keyword,
                    'source' => 'serper',
                    'your_flesch' => $yourFlesch,
                    'competitors' => [],
                    'skipped_reason' => 'no_organic_results',
                    'your_serp' => $this->emptyYourSerpSnapshot(),
                ];
            }

            $yourSerp = $this->resolveYourSerpPosition($pageUrl, $organic);

            $auditedHost = $this->normalizeHostForBenchmark($pageUrl);
            $auditedCanonical = $this->canonicalUrlForBenchmark($pageUrl);

            $candidates = [];
            foreach ($organic as $row) {
                $link = $this->organicRowHttpUrl(is_array($row) ? $row : null);
                if ($link === null) {
                    continue;
                }
                $host = $this->normalizeHostForBenchmark($link);
                if ($auditedHost !== null && $host !== null && $host === $auditedHost) {
                    continue;
                }
                if ($auditedCanonical !== null && $this->canonicalUrlForBenchmark($link) === $auditedCanonical) {
                    continue;
                }
                $candidates[] = [
                    'url' => $link,
                    'title' => is_string($row['title'] ?? null) ? $row['title'] : '',
                    'position' => is_numeric($row['position'] ?? null) ? (int) $row['position'] : null,
                ];
                if (count($candidates) >= 3) {
                    break;
                }
            }

            $competitors = [];
            foreach ($candidates as $entry) {
                $fetch = $this->fetch($entry['url']);
                if (! isset($fetch['body'])) {
                    continue;
                }
                try {
                    $compAuditor = new HtmlAuditor($fetch['body'], $entry['url']);
                    $compContent = $compAuditor->content();
                    $compBody = $compContent['body_text'] ?? '';
                    $compRead = $compAuditor->readability($compBody);
                    $competitors[] = [
                        'url' => $entry['url'],
                        'title' => $entry['title'] !== '' ? $entry['title'] : '',
                        'position' => $entry['position'],
                        'word_count' => isset($compContent['word_count']) ? max(0, (int) $compContent['word_count']) : null,
                        'flesch' => is_numeric($compRead['flesch'] ?? null) ? (float) $compRead['flesch'] : null,
                        'grade' => $compRead['grade'] ?? null,
                    ];
                } catch (\Throwable) {
                    continue;
                }
            }

            return [
                'keyword' => $keyword,
                'source' => 'serper',
                'your_flesch' => $yourFlesch,
                'competitors' => $competitors,
                'skipped_reason' => $competitors === [] ? 'no_competitor_pages_fetched' : null,
                'your_serp' => $yourSerp,
            ];
        } catch (\Throwable $e) {
            Log::warning("PageAuditService: Serper benchmark failed for {$pageUrl}: {$e->getMessage()}");

            return [
                'keyword' => $keywordContext,
                'source' => 'serper',
                'your_flesch' => $yourFlesch,
                'competitors' => [],
                'skipped_reason' => 'benchmark_error',
            ];
        }
    }

    /**
     * @return array{found: bool, position: int|null, on_first_page: bool|null, organic_sample_size: int}
     */
    private function emptyYourSerpSnapshot(): array
    {
        return [
            'found' => false,
            'position' => null,
            'on_first_page' => null,
            'organic_sample_size' => 0,
        ];
    }

    /**
     * Match audited URL against Serper organic rows (host + path; ignores query string differences).
     *
     * @param  list<array<string, mixed>>  $organic
     * @return array{found: bool, position: int|null, on_first_page: bool|null, organic_sample_size: int}
     */
    private function resolveYourSerpPosition(string $pageUrl, array $organic): array
    {
        $auditedCanon = $this->canonicalUrlForBenchmark($pageUrl);
        $totalValid = 0;
        foreach ($organic as $row) {
            if ($this->organicRowHttpUrl($row) !== null) {
                $totalValid++;
            }
        }

        $ordinal = 0;
        foreach ($organic as $row) {
            $link = $this->organicRowHttpUrl($row);
            if ($link === null) {
                continue;
            }
            $ordinal++;
            $rowCanon = $this->canonicalUrlForBenchmark($link);
            $matches = $auditedCanon !== null && $rowCanon !== null && $auditedCanon === $rowCanon;
            if (! $matches && $auditedCanon === null) {
                $matches = strtolower(rtrim($link, '/')) === strtolower(rtrim($pageUrl, '/'));
            }
            if ($matches && is_array($row)) {
                $pos = is_numeric($row['position'] ?? null) ? (int) $row['position'] : $ordinal;

                return [
                    'found' => true,
                    'position' => $pos,
                    'on_first_page' => $pos <= 10,
                    'organic_sample_size' => $totalValid,
                ];
            }
        }

        return [
            'found' => false,
            'position' => null,
            'on_first_page' => null,
            'organic_sample_size' => $totalValid,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    private function organicRowHttpUrl(?array $row): ?string
    {
        if (! is_array($row)) {
            return null;
        }
        $link = $row['link'] ?? '';
        if (! is_string($link) || $link === '') {
            return null;
        }
        $linkLower = strtolower($link);
        if (! str_starts_with($linkLower, 'http://') && ! str_starts_with($linkLower, 'https://')) {
            return null;
        }

        return $link;
    }

    private function normalizeHostForBenchmark(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }
        $host = strtolower($host);

        return preg_replace('/^www\./', '', $host) ?: null;
    }

    private function canonicalUrlForBenchmark(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $host = strtolower((string) $parts['host']);
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = $parts['path'] ?? '/';

        return $host.rtrim((string) $path, '/');
    }

    private function fetchTargetKeywords(int $websiteId, string $pageUrl): array
    {
        return SearchConsoleData::query()
            ->select('query', DB::raw('SUM(clicks) as clicks'), DB::raw('SUM(impressions) as impressions'), DB::raw('AVG(position) as position'))
            ->where('website_id', $websiteId)
            ->where('page', $pageUrl)
            ->where('query', '!=', '')
            ->groupBy('query')
            ->orderByDesc('impressions')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'query' => (string) $row->query,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'position' => round((float) $row->position, 1),
            ])
            ->all();
    }

    private function fetch(string $url): array
    {
        $startedAt = microtime(true);
        try {
            $response = Http::timeout(20)
                ->connectTimeout(10)
                ->withUserAgent('Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/124.0.6367.207 Safari/537.36')
                ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
                ->withOptions(['allow_redirects' => true])
                ->get($url);
            $ttfb = (int) round((microtime(true) - $startedAt) * 1000);
            $body = (string) $response->body();
            $encoding = strtolower((string) $response->header('Content-Encoding'));

            return [
                'body' => $body,
                'http_status' => $response->status(),
                'ttfb_ms' => $ttfb,
                'size' => strlen($body),
                'compression' => $encoding !== '' ? $encoding : null,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
                'http_status' => null,
                'ttfb_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        }
    }

    private function checkLinks(array $links): array
    {
        if (empty($links)) {
            return ['broken' => [], 'checked' => 0, 'skipped' => 0];
        }

        $unique = [];
        foreach ($links as $l) {
            if (count($unique) >= self::MAX_LINKS_CHECKED) {
                break;
            }
            $unique[$l['href']] = $l;
        }
        $toCheck = array_values($unique);
        $skipped = max(0, count($links) - count($toCheck));

        $broken = [];
        foreach (array_chunk($toCheck, self::LINK_POOL_CONCURRENCY) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch) {
                $calls = [];
                foreach ($batch as $i => $link) {
                    $calls[] = $pool->as((string) $i)
                        ->timeout(self::LINK_TIMEOUT)
                        ->connectTimeout(self::LINK_TIMEOUT)
                        ->withUserAgent('Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/124.0.6367.207 Safari/537.36')
                        ->withOptions(['allow_redirects' => true])
                        ->head($link['href']);
                }

                return $calls;
            });

            foreach ($batch as $i => $link) {
                $resp = $responses[(string) $i] ?? null;
                $status = null;
                $error = null;

                if ($resp instanceof Response) {
                    $status = $resp->status();
                    if (in_array($status, [403, 405, 501], true)) {
                        $status = $this->getFallback($link['href']);
                    }
                } else {
                    $error = $resp instanceof \Throwable ? $resp->getMessage() : 'unknown';
                }

                if ($status === null || $status >= 400) {
                    $broken[] = [
                        'href' => $link['href'],
                        'anchor' => $link['anchor'] ?? '',
                        'status' => $status,
                        'error' => $error,
                    ];
                }
            }
        }

        return ['broken' => $broken, 'checked' => count($toCheck), 'skipped' => $skipped];
    }

    private function getFallback(string $url): ?int
    {
        try {
            $resp = Http::timeout(self::LINK_TIMEOUT)
                ->connectTimeout(self::LINK_TIMEOUT)
                ->withUserAgent('Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/124.0.6367.207 Safari/537.36')
                ->withOptions(['allow_redirects' => true])
                ->get($url);

            return $resp->status();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function persistFailure(int $websiteId, string $pageUrl, string $error, ?int $httpStatus, ?int $ttfb): PageAuditReport
    {
        return PageAuditReport::updateOrCreate(
            ['website_id' => $websiteId, 'page' => $pageUrl],
            [
                'status' => 'failed',
                'audited_at' => now(),
                'http_status' => $httpStatus,
                'response_time_ms' => $ttfb,
                'page_size_bytes' => null,
                'error_message' => $error,
                'result' => null,
            ]
        );
    }
}
