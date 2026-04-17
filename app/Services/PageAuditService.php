<?php

namespace App\Services;

use App\Models\PageAuditReport;
use App\Models\SearchConsoleData;
use App\Models\Website;
use App\Support\Audit\HtmlAuditor;
use App\Support\Audit\KeywordStrategyAnalyzer;
use App\Support\Audit\PageLocaleResolver;
use App\Support\Audit\RecommendationEngine;
use App\Support\Audit\SafeHttpGuard;
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

    /** Cap the HTML body we parse to protect libxml / memory. */
    private const MAX_BODY_BYTES = 5_000_000;

    public function __construct(private readonly SafeHttpGuard $guard) {}

    public function audit(int $websiteId, string $pageUrl, ?string $serpTargetKeyword = null, bool $enforceUrlBelongsToWebsite = false): PageAuditReport
    {
        $startedAt = microtime(true);

        $serpKeywordTrimmed = is_string($serpTargetKeyword) ? trim($serpTargetKeyword) : '';
        $serpKeywordArg = $serpKeywordTrimmed !== '' ? $serpKeywordTrimmed : null;
        $failurePrimaryKeyword = $serpKeywordArg;
        $failurePrimarySource = $serpKeywordArg !== null ? 'custom_audit' : null;

        $guardCheck = $this->guard->check($pageUrl);
        if (! $guardCheck['ok']) {
            return $this->persistFailure(
                $websiteId,
                $pageUrl,
                'Audit target rejected: '.($guardCheck['reason'] ?? 'unsafe_url'),
                null,
                null,
                $failurePrimaryKeyword,
                $failurePrimarySource,
            );
        }

        if ($enforceUrlBelongsToWebsite) {
            $website = Website::query()->find($websiteId);
            if (! $website instanceof Website) {
                return $this->persistFailure($websiteId, $pageUrl, 'Website not found.', null, null, $failurePrimaryKeyword, $failurePrimarySource);
            }
            if (! $website->isAuditUrlForThisSite($pageUrl)) {
                return $this->persistFailure(
                    $websiteId,
                    $pageUrl,
                    'The URL must use your website domain (or a subdomain of it).',
                    null,
                    null,
                    $failurePrimaryKeyword,
                    $failurePrimarySource,
                );
            }
        }

        try {
            $fetch = $this->fetch($pageUrl);

            if (! isset($fetch['body'])) {
                return $this->persistFailure(
                    $websiteId,
                    $pageUrl,
                    $fetch['error'] ?? 'Failed to fetch page',
                    $fetch['http_status'] ?? null,
                    $fetch['ttfb_ms'] ?? null,
                    $failurePrimaryKeyword,
                    $failurePrimarySource,
                );
            }

            $html = $fetch['body'];
            $auditor = new HtmlAuditor($html, $pageUrl);

            $metadata = $auditor->metadata();
            $localeSignals = $auditor->localeSignals();
            $pageLocaleResolved = PageLocaleResolver::resolve($localeSignals, $pageUrl);
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
                'stack' => $auditor->technology($fetch['headers'] ?? []),
            ];

            $result = [
                'metadata' => $metadata,
                'page_locale' => [
                    'gl' => $pageLocaleResolved['gl'],
                    'hl' => $pageLocaleResolved['hl'],
                    'source' => $pageLocaleResolved['source'],
                    'bcp47' => $pageLocaleResolved['bcp47'],
                    'hreflang_matched' => $pageLocaleResolved['hreflang_matched'],
                    'signals' => $pageLocaleResolved['signals'],
                ],
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
            ], $serpKeywordArg);

            $benchmark = $this->buildSerperReadabilityBenchmark(
                $pageUrl,
                $result['keywords'],
                is_numeric($readability['flesch'] ?? null) ? (float) $readability['flesch'] : null,
                (int) ($content['word_count'] ?? 0),
                (int) ($images['total'] ?? 0),
                $technical['stack'] ?? null,
                $serpKeywordArg,
                $pageLocaleResolved['gl'],
                $pageLocaleResolved['hl'],
            );
            if ($benchmark !== null) {
                $result['benchmark'] = $benchmark;
            }

            $result['recommendations'] = app(RecommendationEngine::class)->analyze($result);

            $kwBlock = $result['keywords'] ?? [];
            $storedPrimaryKeyword = null;
            $storedPrimarySource = null;
            if (($kwBlock['available'] ?? false) === true) {
                $pq = isset($kwBlock['primary']['query']) ? trim((string) $kwBlock['primary']['query']) : '';
                $storedPrimaryKeyword = $pq !== '' ? mb_substr($pq, 0, 200) : null;
                $src = $kwBlock['primary_source'] ?? null;
                $storedPrimarySource = is_string($src) && $src !== '' ? mb_substr($src, 0, 32) : null;
            }

            return PageAuditReport::updateOrCreate(
                ['website_id' => $websiteId, 'page_hash' => hash('sha256', $pageUrl)],
                [
                    'page' => mb_substr($pageUrl, 0, 700),
                    'status' => 'completed',
                    'audited_at' => now(),
                    'http_status' => $fetch['http_status'],
                    'response_time_ms' => $fetch['ttfb_ms'],
                    'page_size_bytes' => $fetch['size'],
                    'error_message' => null,
                    'primary_keyword' => $storedPrimaryKeyword,
                    'primary_keyword_source' => $storedPrimarySource,
                    'result' => $result,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("PageAuditService failed for website {$websiteId} page {$pageUrl}: {$e->getMessage()}");

            return $this->persistFailure(
                $websiteId,
                $pageUrl,
                $e->getMessage(),
                null,
                (int) round((microtime(true) - $startedAt) * 1000),
                $failurePrimaryKeyword,
                $failurePrimarySource,
            );
        }
    }

    /**
     * Optional SERP readability benchmark via Serper (fail-closed: never throws).
     *
     * @param  array<string, mixed>  $keywordsPayload
     * @return array<string, mixed>|null null when Serper is not configured
     */
    private function buildSerperReadabilityBenchmark(string $pageUrl, array $keywordsPayload, ?float $yourFlesch, int $yourWordCount, int $yourImageCount, ?array $yourStack = null, ?string $serpKeywordOverride = null, ?string $serpGl = null, ?string $serpHl = null): ?array
    {
        $apiKey = config('services.serper.key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        $keywordContext = null;

        try {
            $serpLocaleOut = $this->compactSerpLocale($serpGl, $serpHl);
            $override = $serpKeywordOverride !== null ? trim($serpKeywordOverride) : '';
            $useManualSerpKeyword = $override !== '';
            $keywordSource = null;

            if (! $useManualSerpKeyword) {
                if (empty($keywordsPayload['available']) || empty($keywordsPayload['primary']['query'])) {
                    return [
                        'keyword' => null,
                        'keyword_source' => null,
                        'source' => 'serper',
                        'your_flesch' => $yourFlesch,
                        'competitors' => [],
                        'skipped_reason' => 'no_primary_keyword',
                        'serp_locale' => $serpLocaleOut,
                    ];
                }
                $keyword = trim((string) $keywordsPayload['primary']['query']);
                $keywordSource = 'gsc_primary';
            } else {
                $keyword = $override;
                $keywordSource = 'manual';
            }

            $keywordContext = $keyword !== '' ? $keyword : null;
            if ($keyword === '') {
                return [
                    'keyword' => null,
                    'keyword_source' => null,
                    'source' => 'serper',
                    'your_flesch' => $yourFlesch,
                    'competitors' => [],
                    'skipped_reason' => 'no_primary_keyword',
                    'serp_locale' => $serpLocaleOut,
                ];
            }

            $payload = app(SerperSearchClient::class)->search($keyword, 20, $serpGl, $serpHl);
            if ($payload === null) {
                return [
                    'keyword' => $keyword,
                    'keyword_source' => $keywordSource,
                    'source' => 'serper',
                    'your_flesch' => $yourFlesch,
                    'competitors' => [],
                    'skipped_reason' => 'serper_request_failed',
                    'your_serp' => $this->emptyYourSerpSnapshot(),
                    'serp_locale' => $serpLocaleOut,
                ];
            }

            $organic = $payload['organic'] ?? [];
            if (! is_array($organic) || $organic === []) {
                return [
                    'keyword' => $keyword,
                    'keyword_source' => $keywordSource,
                    'source' => 'serper',
                    'your_flesch' => $yourFlesch,
                    'competitors' => [],
                    'skipped_reason' => 'no_organic_results',
                    'your_serp' => $this->emptyYourSerpSnapshot(),
                    'serp_locale' => $serpLocaleOut,
                ];
            }

            $yourSerp = $this->resolveYourSerpPosition($pageUrl, $organic);

            $candidates = [];
            foreach ($organic as $row) {
                $link = $this->organicRowHttpUrl(is_array($row) ? $row : null);
                if ($link === null) {
                    continue;
                }
                if ($this->organicHostMatchesAuditedSite($pageUrl, $link)) {
                    continue;
                }
                if (! $this->guard->check($link)['ok']) {
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
                    $compImages = $compAuditor->images();
                    $compStack = $compAuditor->technology($fetch['headers'] ?? []);
                    $competitors[] = [
                        'url' => $entry['url'],
                        'title' => $entry['title'] !== '' ? $entry['title'] : '',
                        'position' => $entry['position'],
                        'word_count' => isset($compContent['word_count']) ? max(0, (int) $compContent['word_count']) : null,
                        'image_count' => max(0, (int) ($compImages['total'] ?? 0)),
                        'flesch' => is_numeric($compRead['flesch'] ?? null) ? (float) $compRead['flesch'] : null,
                        'grade' => $compRead['grade'] ?? null,
                        'stack_label' => $compStack['label'] ?? 'Unknown',
                        'stack_type' => $compStack['type'] ?? 'unknown',
                    ];
                } catch (\Throwable) {
                    continue;
                }
            }

            $gapTable = $this->buildBenchmarkGapTable($yourWordCount, $yourFlesch, $yourImageCount, $competitors, $yourStack);

            return [
                'keyword' => $keyword,
                'keyword_source' => $keywordSource,
                'source' => 'serper',
                'your_flesch' => $yourFlesch,
                'your_word_count' => $yourWordCount,
                'your_image_count' => $yourImageCount,
                'competitors' => $competitors,
                'skipped_reason' => $competitors === [] ? 'no_competitor_pages_fetched' : null,
                'your_serp' => $yourSerp,
                'gap_table' => $gapTable,
                'serp_locale' => $serpLocaleOut,
            ];
        } catch (\Throwable $e) {
            Log::warning("PageAuditService: Serper benchmark failed for {$pageUrl}: {$e->getMessage()}");

            return [
                'keyword' => $keywordContext,
                'keyword_source' => null,
                'source' => 'serper',
                'your_flesch' => $yourFlesch,
                'competitors' => [],
                'skipped_reason' => 'benchmark_error',
                'serp_locale' => $this->compactSerpLocale($serpGl, $serpHl),
            ];
        }
    }

    /**
     * @return array{gl?: string, hl?: string}
     */
    private function compactSerpLocale(?string $gl, ?string $hl): array
    {
        $out = [];
        $g = is_string($gl) ? strtolower(trim($gl)) : '';
        if ($g !== '' && strlen($g) === 2 && ctype_alpha($g)) {
            $out['gl'] = $g;
        }
        $h = is_string($hl) ? strtolower(trim($hl)) : '';
        if ($h !== '' && preg_match('/^[a-z]{2}(-[a-z0-9]{2,8})?$/', $h) === 1) {
            $out['hl'] = $h;
        }

        return $out;
    }

    /**
     * Compare audited page to averages from fetched competitor HTML (word count, Flesch, image count).
     *
     * @param  list<array<string, mixed>>  $competitors
     * @return array{rows: list<array<string, mixed>>}|null
     */
    private function buildBenchmarkGapTable(int $yourWords, ?float $yourFlesch, int $yourImages, array $competitors, ?array $yourStack = null): ?array
    {
        if ($competitors === []) {
            return null;
        }

        $rows = [];

        $wcVals = [];
        foreach ($competitors as $c) {
            if (is_array($c) && isset($c['word_count']) && is_numeric($c['word_count'])) {
                $wcVals[] = (float) $c['word_count'];
            }
        }
        if ($wcVals !== []) {
            $avg = array_sum($wcVals) / count($wcVals);
            $delta = $yourWords - $avg;
            $rows[] = [
                'key' => 'word_count',
                'metric' => 'Word count',
                'yours' => $yourWords,
                'market_avg' => round($avg, 1),
                'delta' => round($delta, 1),
                'status' => $delta < -1 ? 'Add content' : ($delta > 1 ? 'Above sample' : 'Aligned'),
            ];
        }

        $fVals = [];
        foreach ($competitors as $c) {
            if (is_array($c) && isset($c['flesch']) && is_numeric($c['flesch'])) {
                $fVals[] = (float) $c['flesch'];
            }
        }
        if ($fVals !== [] && is_numeric($yourFlesch)) {
            $avg = array_sum($fVals) / count($fVals);
            $delta = $yourFlesch - $avg;
            $rows[] = [
                'key' => 'flesch',
                'metric' => 'Readability (Flesch)',
                'yours' => round($yourFlesch, 1),
                'market_avg' => round($avg, 1),
                'delta' => round($delta, 1),
                'status' => $delta > 2 ? 'Better UX' : ($delta < -2 ? 'Tighten copy' : 'Similar'),
            ];
        }

        $imgVals = [];
        foreach ($competitors as $c) {
            if (is_array($c) && array_key_exists('image_count', $c) && is_numeric($c['image_count'])) {
                $imgVals[] = (float) $c['image_count'];
            }
        }
        if ($imgVals !== []) {
            $avg = array_sum($imgVals) / count($imgVals);
            $delta = $yourImages - $avg;
            $rows[] = [
                'key' => 'images',
                'metric' => 'Images',
                'yours' => $yourImages,
                'market_avg' => round($avg, 1),
                'delta' => round($delta, 1),
                'status' => $delta < -0.5 ? 'Add visuals' : ($delta > 0.5 ? 'Above sample' : 'Aligned'),
            ];
        }

        $stackRow = $this->buildStackGapRow($yourStack, $competitors);
        if ($stackRow !== null) {
            $rows[] = $stackRow;
        }

        return $rows === [] ? null : ['rows' => $rows];
    }

    /**
     * @param  array{label: string, type: string}|null  $yourStack
     * @param  list<array<string, mixed>>  $competitors
     * @return array<string, mixed>|null
     */
    private function buildStackGapRow(?array $yourStack, array $competitors): ?array
    {
        $yourType = is_array($yourStack) ? (string) ($yourStack['type'] ?? 'unknown') : 'unknown';
        $yourLabel = is_array($yourStack) ? (string) ($yourStack['label'] ?? 'Unknown') : 'Unknown';
        if ($yourType === 'unknown' || $yourLabel === 'Unknown') {
            return null;
        }

        $compTypes = [];
        $compLabels = [];
        foreach ($competitors as $c) {
            $ct = (string) ($c['stack_type'] ?? 'unknown');
            $cl = (string) ($c['stack_label'] ?? 'Unknown');
            if ($ct === 'unknown' || $cl === 'Unknown') {
                continue;
            }
            $compTypes[] = $ct;
            $compLabels[] = $cl;
        }
        if (count($compTypes) < 2) {
            return null;
        }

        $labelCounts = array_count_values($compLabels);
        arsort($labelCounts);
        $topLabel = array_key_first($labelCounts);
        $topCount = $labelCounts[$topLabel];
        $summary = $topCount > 1 ? "{$topLabel} ×{$topCount}" : $topLabel;

        $typeCounts = array_count_values($compTypes);
        arsort($typeCounts);
        $dominantType = array_key_first($typeCounts);

        $deltaKind = 'parity';
        if ($yourType === 'modern' && $dominantType === 'cms') {
            $deltaKind = 'moat';
        } elseif ($yourType === 'cms' && $dominantType === 'modern') {
            $deltaKind = 'disadvantage';
        }

        return [
            'key' => 'stack',
            'metric' => 'Tech stack',
            'yours' => $yourLabel,
            'market_avg' => $summary,
            'delta' => null,
            'delta_kind' => $deltaKind,
            'status' => match ($deltaKind) {
                'moat' => 'Competitive moat',
                'disadvantage' => 'Stack gap',
                default => 'Parity',
            },
        ];
    }

    /**
     * @return array{found: bool, position: int|null, on_first_page: bool|null, organic_sample_size: int, matched_listing_url: string|null, matched_listing_title: string|null, matched_listing_snippet: string|null, matched_listing_display: string|null}
     */
    private function emptyYourSerpSnapshot(): array
    {
        return [
            'found' => false,
            'position' => null,
            'on_first_page' => null,
            'organic_sample_size' => 0,
            'matched_listing_url' => null,
            'matched_listing_title' => null,
            'matched_listing_snippet' => null,
            'matched_listing_display' => null,
        ];
    }

    /**
     * Match audited site against Serper organic rows by **host** (www-normalized), not full URL path,
     * so e.g. an audited deep URL still matches when the sample lists the homepage or another path on the same domain.
     * Picks the best (lowest) reported position among matching rows and returns that row’s listing URL/title.
     *
     * @param  list<array<string, mixed>>  $organic
     * @return array{found: bool, position: int|null, on_first_page: bool|null, organic_sample_size: int, matched_listing_url: string|null, matched_listing_title: string|null, matched_listing_snippet: string|null, matched_listing_display: string|null}
     */
    private function resolveYourSerpPosition(string $pageUrl, array $organic): array
    {
        $totalValid = 0;
        foreach ($organic as $row) {
            if ($this->organicRowHttpUrl($row) !== null) {
                $totalValid++;
            }
        }

        $bestPos = null;
        $bestLink = null;
        $bestTitle = null;
        $bestSnippet = null;
        $bestDisplay = null;
        $ordinal = 0;
        foreach ($organic as $row) {
            $link = $this->organicRowHttpUrl($row);
            if ($link === null) {
                continue;
            }
            $ordinal++;
            if (! $this->organicHostMatchesAuditedSite($pageUrl, $link)) {
                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $pos = is_numeric($row['position'] ?? null) ? (int) $row['position'] : $ordinal;
            if ($bestPos === null || $pos < $bestPos) {
                $bestPos = $pos;
                $bestLink = $link;
                $t = $row['title'] ?? '';
                $bestTitle = is_string($t) && trim($t) !== '' ? trim($t) : null;
                $bestSnippet = $this->organicRowSnippet($row);
                $bestDisplay = $this->organicSerpDisplayLine($row, $link);
            }
        }

        if ($bestPos !== null && $bestLink !== null) {
            return [
                'found' => true,
                'position' => $bestPos,
                'on_first_page' => $bestPos <= 10,
                'organic_sample_size' => $totalValid,
                'matched_listing_url' => $bestLink,
                'matched_listing_title' => $bestTitle,
                'matched_listing_snippet' => $bestSnippet,
                'matched_listing_display' => $bestDisplay,
            ];
        }

        return [
            'found' => false,
            'position' => null,
            'on_first_page' => null,
            'organic_sample_size' => $totalValid,
            'matched_listing_url' => null,
            'matched_listing_title' => null,
            'matched_listing_snippet' => null,
            'matched_listing_display' => null,
        ];
    }

    /**
     * True when the organic result is on the same host or a parent/child subdomain of the audited URL’s host
     * (e.g. audited https://blog.example.com/a and organic https://example.com/ → match).
     */
    private function organicHostMatchesAuditedSite(string $auditedPageUrl, string $organicUrl): bool
    {
        $auditedHost = $this->normalizeHostForBenchmark($auditedPageUrl);
        $organicHost = $this->normalizeHostForBenchmark($organicUrl);
        if ($auditedHost === null || $organicHost === null) {
            return false;
        }
        if ($auditedHost === $organicHost) {
            return true;
        }

        return str_ends_with($auditedHost, '.'.$organicHost)
            || str_ends_with($organicHost, '.'.$auditedHost);
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

    /**
     * @param  array<string, mixed>  $row
     */
    private function organicRowSnippet(array $row): ?string
    {
        foreach (['snippet', 'description'] as $key) {
            $v = $row[$key] ?? null;
            if (is_string($v)) {
                $s = trim($v);
                if ($s !== '') {
                    return mb_substr($s, 0, 320);
                }
            }
        }

        return null;
    }

    /**
     * Green-line style URL as shown in Google (Serper: displayLink, etc.; else breadcrumb from link).
     *
     * @param  array<string, mixed>  $row
     */
    private function organicSerpDisplayLine(array $row, string $link): string
    {
        foreach (['displayLink', 'displayedLink', 'displayUrl'] as $key) {
            $v = $row[$key] ?? null;
            if (is_string($v)) {
                $s = trim($v);
                if ($s !== '') {
                    return mb_substr($s, 0, 200);
                }
            }
        }

        return $this->breadcrumbStyleUrlDisplay($link);
    }

    private function breadcrumbStyleUrlDisplay(string $url): string
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '');
        $trimmedPath = trim($path, '/');

        return $trimmedPath !== ''
            ? $host.' › '.str_replace('/', ' › ', $trimmedPath)
            : $host;
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

        $guardCheck = $this->guard->check($url);
        if (! $guardCheck['ok']) {
            return [
                'error' => 'blocked: '.($guardCheck['reason'] ?? 'unsafe_url'),
                'http_status' => null,
                'ttfb_ms' => 0,
            ];
        }

        try {
            $response = Http::timeout(20)
                ->connectTimeout(10)
                ->withUserAgent('Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/124.0.6367.207 Safari/537.36')
                ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
                ->withOptions([
                    // Manual redirect handling: follow up to 5 hops, but re-guard each.
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => true,
                        'referer' => false,
                        'protocols' => ['http', 'https'],
                        'track_redirects' => true,
                        'on_redirect' => function ($request, $response, $uri) {
                            $check = $this->guard->check((string) $uri);
                            if (! $check['ok']) {
                                throw new \RuntimeException('blocked redirect: '.($check['reason'] ?? 'unsafe_url'));
                            }
                        },
                    ],
                ])
                ->get($url);
            $ttfb = (int) round((microtime(true) - $startedAt) * 1000);
            $fullBody = (string) $response->body();
            $fullSize = strlen($fullBody);
            $body = $fullSize > self::MAX_BODY_BYTES ? substr($fullBody, 0, self::MAX_BODY_BYTES) : $fullBody;
            if ($fullSize > self::MAX_BODY_BYTES) {
                Log::info("PageAuditService: truncated oversized response body for {$url} ({$fullSize} -> ".self::MAX_BODY_BYTES.' bytes)');
            }
            $encoding = strtolower((string) $response->header('Content-Encoding'));

            $stackHeaders = [];
            foreach (['server', 'x-powered-by', 'x-generator', 'via', 'cf-cache-status'] as $hName) {
                $val = (string) $response->header($hName);
                if ($val !== '') {
                    $stackHeaders[$hName] = $val;
                }
            }

            return [
                'body' => $body,
                'http_status' => $response->status(),
                'ttfb_ms' => $ttfb,
                'size' => $fullSize,
                'compression' => $encoding !== '' ? $encoding : null,
                'headers' => $stackHeaders,
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
        $allCandidates = array_values($unique);
        $skipped = max(0, count($links) - count($allCandidates));

        $broken = [];
        $toCheck = [];
        foreach ($allCandidates as $link) {
            $check = $this->guard->check((string) ($link['href'] ?? ''));
            if (! $check['ok']) {
                $broken[] = [
                    'href' => $link['href'] ?? '',
                    'anchor' => $link['anchor'] ?? '',
                    'status' => 'blocked',
                    'error' => $check['reason'] ?? 'unsafe_url',
                ];

                continue;
            }
            $toCheck[] = $link;
        }

        foreach (array_chunk($toCheck, self::LINK_POOL_CONCURRENCY) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch) {
                $calls = [];
                foreach ($batch as $i => $link) {
                    $calls[] = $pool->as((string) $i)
                        ->timeout(self::LINK_TIMEOUT)
                        ->connectTimeout(self::LINK_TIMEOUT)
                        ->withUserAgent('Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/124.0.6367.207 Safari/537.36')
                        ->withOptions([
                            'allow_redirects' => [
                                'max' => 3,
                                'strict' => true,
                                'referer' => false,
                                'protocols' => ['http', 'https'],
                                'on_redirect' => function ($request, $response, $uri) {
                                    $check = $this->guard->check((string) $uri);
                                    if (! $check['ok']) {
                                        throw new \RuntimeException('blocked redirect: '.($check['reason'] ?? 'unsafe_url'));
                                    }
                                },
                            ],
                        ])
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
        $check = $this->guard->check($url);
        if (! $check['ok']) {
            return null;
        }

        try {
            $resp = Http::timeout(self::LINK_TIMEOUT)
                ->connectTimeout(self::LINK_TIMEOUT)
                ->withUserAgent('Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Googlebot/2.1; +http://www.google.com/bot.html) Chrome/124.0.6367.207 Safari/537.36')
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'strict' => true,
                        'referer' => false,
                        'protocols' => ['http', 'https'],
                        'on_redirect' => function ($request, $response, $uri) {
                            $check = $this->guard->check((string) $uri);
                            if (! $check['ok']) {
                                throw new \RuntimeException('blocked redirect: '.($check['reason'] ?? 'unsafe_url'));
                            }
                        },
                    ],
                ])
                ->get($url);

            return $resp->status();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function persistFailure(
        int $websiteId,
        string $pageUrl,
        string $error,
        ?int $httpStatus,
        ?int $ttfb,
        ?string $primaryKeyword = null,
        ?string $primaryKeywordSource = null,
    ): PageAuditReport {
        $pk = $primaryKeyword !== null && trim($primaryKeyword) !== '' ? mb_substr(trim($primaryKeyword), 0, 200) : null;
        $pks = $primaryKeywordSource !== null && trim($primaryKeywordSource) !== '' ? mb_substr(trim($primaryKeywordSource), 0, 32) : null;
        if ($pk === null) {
            $pks = null;
        }

        return PageAuditReport::updateOrCreate(
            ['website_id' => $websiteId, 'page_hash' => hash('sha256', $pageUrl)],
            [
                'page' => mb_substr($pageUrl, 0, 700),
                'status' => 'failed',
                'audited_at' => now(),
                'http_status' => $httpStatus,
                'response_time_ms' => $ttfb,
                'page_size_bytes' => null,
                'error_message' => mb_substr($error, 0, 1000),
                'primary_keyword' => $pk,
                'primary_keyword_source' => $pks,
                'result' => null,
            ]
        );
    }
}
