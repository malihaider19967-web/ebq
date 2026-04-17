<?php

namespace App\Services;

use App\Models\PageAuditReport;
use App\Support\Audit\HtmlAuditor;
use App\Support\Audit\RecommendationEngine;
use Illuminate\Http\Client\Pool;
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

                if ($resp instanceof \Illuminate\Http\Client\Response) {
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
