<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the standalone ebq-intelegence Lighthouse API.
 *
 * Design rules:
 * - Never throw. An audit must complete without CWV data if the service is
 *   down, misconfigured, or times out. Return null and log.
 * - One HTTP call fetches both strategies (mobile + desktop) via /audit/batch.
 *   Keeps the Laravel side simple and lets the Node side reuse Chrome warm
 *   across the two runs.
 * - Normalizes the response to a shape that's trivial to persist in
 *   PageAuditReport::result['core_web_vitals'].
 */
class LighthouseClient
{
    public function isConfigured(): bool
    {
        $url = config('services.lighthouse.url');
        $key = config('services.lighthouse.key');

        return is_string($url) && $url !== ''
            && is_string($key) && $key !== '';
    }

    /**
     * Fetch mobile + desktop CWV for a URL.
     *
     * @return array<string, mixed>|null  Null on any failure — caller stores nothing.
     */
    public function fetchMobileAndDesktop(string $url): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $base = rtrim((string) config('services.lighthouse.url'), '/');
        $key = (string) config('services.lighthouse.key');
        $timeout = (int) config('services.lighthouse.timeout', 90);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(5)
                ->withHeaders([
                    'X-Api-Key' => $key,
                    'Content-Type' => 'application/json',
                ])
                ->post($base.'/audit/batch', [
                    'items' => [
                        ['url' => $url, 'strategy' => 'mobile'],
                        ['url' => $url, 'strategy' => 'desktop'],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('LighthouseClient: request failed: '.$e->getMessage(), ['url' => $url]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('LighthouseClient: HTTP '.$response->status(), [
                'url' => $url,
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return null;
        }

        try {
            $payload = $response->json();
        } catch (\Throwable $e) {
            Log::warning('LighthouseClient: invalid JSON: '.$e->getMessage(), ['url' => $url]);

            return null;
        }
        if (! is_array($payload) || ! isset($payload['items']) || ! is_array($payload['items'])) {
            return null;
        }

        $byStrategy = ['mobile' => null, 'desktop' => null];
        foreach ($payload['items'] as $item) {
            if (! is_array($item) || ! ($item['ok'] ?? false) || ! is_array($item['result'] ?? null)) {
                continue;
            }
            $strategy = $item['result']['strategy'] ?? null;
            if ($strategy === 'mobile' || $strategy === 'desktop') {
                $byStrategy[$strategy] = $this->normalizeOne($item['result']);
            }
        }

        // At least one strategy must have succeeded — otherwise storing an empty
        // block is worse than nothing (it hides the "not available" UI branch).
        if ($byStrategy['mobile'] === null && $byStrategy['desktop'] === null) {
            return null;
        }

        return [
            'mobile' => $byStrategy['mobile'],
            'desktop' => $byStrategy['desktop'],
            'fetched_at' => now()->toIso8601String(),
            'source' => 'lighthouse-local',
        ];
    }

    /**
     * Full PageSpeed-Insights-style report for the standalone tool.
     *
     * Unlike {@see fetchMobileAndDesktop()} (which returns the trimmed CWV
     * contract the page-audit pipeline persists), this asks the Lighthouse
     * service for the COMPLETE report (`?raw=1`) across all four scored
     * categories, then reshapes the ~1MB LHR into a compact structure the
     * UI can render like PSI: category gauges, lab metrics, opportunities
     * (with estimated savings), diagnostics, per-category failed audits and
     * a final screenshot. The heavy raw payload is discarded after parsing.
     *
     * Two sequential calls (mobile, desktop) — never throws; returns null
     * if neither strategy produced a usable report.
     *
     * @return array<string, mixed>|null
     */
    public function fetchFullReport(string $url): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $mobile = $this->fetchStrategyReport($url, 'mobile');
        $desktop = $this->fetchStrategyReport($url, 'desktop');

        if ($mobile === null && $desktop === null) {
            return null;
        }

        return [
            'mobile' => $mobile,
            'desktop' => $desktop,
            'fetched_at' => now()->toIso8601String(),
            'lighthouse_version' => $mobile['lighthouse_version'] ?? $desktop['lighthouse_version'] ?? null,
            'source' => 'lighthouse-local',
        ];
    }

    /**
     * Run ONE strategy (mobile|desktop) with the full category set and parse
     * the raw LHR into the compact render-ready structure. Public so the
     * async PageSpeed job can run each strategy as its own short-lived job —
     * a single synchronous mobile+desktop run is long enough to hit the
     * queue-worker timeout and Cloudflare's proxy timeout on heavy sites.
     *
     * @return array<string, mixed>|null
     */
    public function fetchStrategyReport(string $url, string $strategy, ?int $maxSeconds = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $base = rtrim((string) config('services.lighthouse.url'), '/');
        $key = (string) config('services.lighthouse.key');
        // Cap below the queue worker's --timeout (90s) so a slow run returns
        // null (→ "this strategy failed") instead of the worker killing the
        // whole job and losing the other strategy too. Callers running BOTH
        // strategies in one job (the public guest tool) pass a tighter cap so
        // mobile + desktop together still fit inside the worker cycle.
        $timeout = min((int) config('services.lighthouse.timeout', 90), $maxSeconds ?? 80);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(5)
                ->withHeaders(['X-Api-Key' => $key, 'Content-Type' => 'application/json'])
                ->post($base.'/audit?raw=1', [
                    'url' => $url,
                    'strategy' => $strategy,
                    'categories' => ['performance', 'accessibility', 'best-practices', 'seo'],
                ]);
        } catch (\Throwable $e) {
            Log::warning('LighthouseClient: full '.$strategy.' request failed: '.$e->getMessage(), ['url' => $url]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('LighthouseClient: full '.$strategy.' HTTP '.$response->status(), ['url' => $url]);

            return null;
        }

        $payload = $response->json();
        $lhr = is_array($payload) && is_array($payload['raw'] ?? null) ? $payload['raw'] : null;
        if ($lhr === null) {
            return null;
        }

        return $this->parseFullLhr($lhr, $strategy);
    }

    /**
     * Collapse a full Lighthouse Result object into a compact, render-ready
     * structure. Keeps only what the PSI-style UI needs and caps list sizes
     * so the Livewire payload stays small.
     *
     * @param  array<string, mixed>  $lhr
     * @return array<string, mixed>
     */
    private function parseFullLhr(array $lhr, string $strategy): array
    {
        $audits = is_array($lhr['audits'] ?? null) ? $lhr['audits'] : [];
        $categories = is_array($lhr['categories'] ?? null) ? $lhr['categories'] : [];

        $catScore = function (string $id) use ($categories): ?int {
            $score = $categories[$id]['score'] ?? null;

            return is_numeric($score) ? (int) round(((float) $score) * 100) : null;
        };

        // ── Lab metrics (the six PSI headline metrics) ──────────────────
        $metricMap = [
            ['key' => 'fcp', 'audit' => 'first-contentful-paint', 'label' => 'First Contentful Paint'],
            ['key' => 'lcp', 'audit' => 'largest-contentful-paint', 'label' => 'Largest Contentful Paint'],
            ['key' => 'tbt', 'audit' => 'total-blocking-time', 'label' => 'Total Blocking Time'],
            ['key' => 'cls', 'audit' => 'cumulative-layout-shift', 'label' => 'Cumulative Layout Shift'],
            ['key' => 'si', 'audit' => 'speed-index', 'label' => 'Speed Index'],
            ['key' => 'tti', 'audit' => 'interactive', 'label' => 'Time to Interactive'],
        ];
        $metrics = [];
        foreach ($metricMap as $m) {
            $a = $audits[$m['audit']] ?? null;
            if (! is_array($a)) {
                continue;
            }
            $metrics[] = [
                'key' => $m['key'],
                'label' => $m['label'],
                'display' => is_string($a['displayValue'] ?? null) ? $a['displayValue'] : '—',
                'rating' => $this->rating($a['score'] ?? null),
            ];
        }

        // ── Opportunities: improvable audits with estimated time savings ─
        $opportunities = [];
        foreach ($audits as $id => $a) {
            if (! is_array($a) || ($a['details']['type'] ?? null) !== 'opportunity') {
                continue;
            }
            $score = $a['score'] ?? null;
            if ($score === 1 || $score === null) {
                continue; // already passing / not applicable
            }
            $opportunities[] = [
                'id' => $id,
                'title' => (string) ($a['title'] ?? $id),
                'savings_ms' => (int) round((float) ($a['details']['overallSavingsMs'] ?? 0)),
                'display' => is_string($a['displayValue'] ?? null) ? $a['displayValue'] : null,
                'description' => $this->plainDescription($a['description'] ?? null),
                'rating' => $this->rating($score),
                'resources' => $this->extractDetailsTable($a['details'] ?? null),
            ];
        }
        usort($opportunities, fn ($a, $b) => $b['savings_ms'] <=> $a['savings_ms']);
        $opportunities = array_slice($opportunities, 0, 12);

        // ── Diagnostics: non-passing performance audits in the diag group ─
        $diagnostics = [];
        foreach (($categories['performance']['auditRefs'] ?? []) as $ref) {
            if (($ref['group'] ?? null) !== 'diagnostics') {
                continue;
            }
            $a = $audits[$ref['id'] ?? ''] ?? null;
            if (! is_array($a) || ($a['details']['type'] ?? null) === 'opportunity') {
                continue;
            }
            $score = $a['score'] ?? null;
            if ($score === 1) {
                continue;
            }
            $diagnostics[] = [
                'title' => (string) ($a['title'] ?? ($ref['id'] ?? '')),
                'display' => is_string($a['displayValue'] ?? null) ? $a['displayValue'] : null,
                'description' => $this->plainDescription($a['description'] ?? null),
                'rating' => $this->rating($score),
                'resources' => $this->extractDetailsTable($a['details'] ?? null),
            ];
        }
        $diagnostics = array_slice($diagnostics, 0, 10);

        // ── Failed audits for the non-performance categories ────────────
        $failedAudits = [];
        foreach (['accessibility' => 'accessibility', 'best-practices' => 'best_practices', 'seo' => 'seo'] as $catId => $outKey) {
            $list = [];
            foreach (($categories[$catId]['auditRefs'] ?? []) as $ref) {
                $a = $audits[$ref['id'] ?? ''] ?? null;
                if (! is_array($a)) {
                    continue;
                }
                $score = $a['score'] ?? null;
                if (! is_numeric($score) || (float) $score >= 1) {
                    continue; // only show what actually failed
                }
                $list[] = [
                    'title' => (string) ($a['title'] ?? ($ref['id'] ?? '')),
                    'description' => $this->plainDescription($a['description'] ?? null),
                ];
                if (count($list) >= 12) {
                    break;
                }
            }
            $failedAudits[$outKey] = $list;
        }

        $screenshot = $audits['final-screenshot']['details']['data'] ?? null;

        return [
            'strategy' => $strategy,
            'lighthouse_version' => is_string($lhr['lighthouseVersion'] ?? null) ? $lhr['lighthouseVersion'] : null,
            'scores' => [
                'performance' => $catScore('performance'),
                'accessibility' => $catScore('accessibility'),
                'best_practices' => $catScore('best-practices'),
                'seo' => $catScore('seo'),
            ],
            'metrics' => $metrics,
            'opportunities' => $opportunities,
            'diagnostics' => $diagnostics,
            'failed_audits' => $failedAudits,
            'screenshot' => is_string($screenshot) && str_starts_with($screenshot, 'data:image') ? $screenshot : null,
        ];
    }

    /**
     * Extract the offending-resource table from a Lighthouse audit's
     * `details` (the URLs / scripts / images PSI lists under each
     * opportunity & diagnostic), formatted for display. Returns null when
     * the audit has no tabular detail. Capped to 8 rows / 4 columns to
     * keep the Livewire payload small.
     *
     * @return array{columns: array<int, array{label: string, numeric: bool}>, rows: array<int, array<int, array{text: string, is_url: bool}>>, total: int, truncated: bool}|null
     */
    private function extractDetailsTable(mixed $details): ?array
    {
        if (! is_array($details)) {
            return null;
        }

        $headings = $details['headings'] ?? null;
        $items = $details['items'] ?? null;
        if (! is_array($headings) || ! is_array($items) || $headings === [] || $items === []) {
            return null;
        }

        $cols = [];
        foreach ($headings as $h) {
            if (count($cols) >= 4 || ! is_array($h)) {
                continue;
            }
            // Lighthouse renamed fields over versions: key/valueType (new),
            // itemType/text (old). A null key is a layout spacer — skip it.
            $key = $h['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }
            $type = (string) ($h['valueType'] ?? $h['itemType'] ?? 'text');
            $cols[] = [
                'key' => $key,
                'label' => (string) ($h['label'] ?? $h['text'] ?? $key),
                'type' => $type,
                'numeric' => in_array($type, ['bytes', 'ms', 'timespanMs', 'numeric'], true),
            ];
        }
        if ($cols === []) {
            return null;
        }

        $rows = [];
        foreach ($items as $item) {
            if (count($rows) >= 8) {
                break;
            }
            if (! is_array($item)) {
                continue;
            }
            $cells = [];
            foreach ($cols as $col) {
                $cells[] = $this->formatCell($item[$col['key']] ?? null, $col['type']);
            }
            $rows[] = $cells;
        }
        if ($rows === []) {
            return null;
        }

        return [
            'columns' => array_map(fn ($c) => ['label' => $c['label'], 'numeric' => $c['numeric']], $cols),
            'rows' => $rows,
            'total' => count($items),
            'truncated' => count($items) > count($rows),
        ];
    }

    /**
     * Format one detail-table cell value by its Lighthouse value type.
     *
     * @return array{text: string, is_url: bool}
     */
    private function formatCell(mixed $value, string $type): array
    {
        // Object values (DOM nodes, link/code/source-location objects):
        // pull out the most useful string.
        if (is_array($value)) {
            $value = $value['url'] ?? $value['snippet'] ?? $value['text'] ?? $value['value'] ?? $value['selector'] ?? null;
            if (! is_string($value)) {
                return ['text' => '—', 'is_url' => false];
            }
        }

        if ($value === null || $value === '') {
            return ['text' => '—', 'is_url' => false];
        }

        return match ($type) {
            'url' => ['text' => (string) $value, 'is_url' => true],
            'bytes' => ['text' => $this->formatBytes((float) $value), 'is_url' => false],
            'ms', 'timespanMs' => ['text' => $this->formatMs((float) $value), 'is_url' => false],
            'numeric' => ['text' => is_numeric($value) ? (string) round((float) $value, 2) : (string) $value, 'is_url' => false],
            default => ['text' => mb_substr((string) $value, 0, 140), 'is_url' => str_starts_with((string) $value, 'http')],
        };
    }

    private function formatBytes(float $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return round($bytes).' B';
    }

    private function formatMs(float $ms): string
    {
        return $ms >= 1000 ? number_format($ms / 1000, 2).' s' : round($ms).' ms';
    }

    /**
     * Map a Lighthouse 0–1 audit score to a PSI severity bucket.
     */
    private function rating(mixed $score): string
    {
        if (! is_numeric($score)) {
            return 'na';
        }
        $s = (float) $score;
        if ($s >= 0.9) {
            return 'good';
        }
        if ($s >= 0.5) {
            return 'average';
        }

        return 'poor';
    }

    /**
     * Strip markdown links/markup from a Lighthouse audit description so it
     * renders as clean plain text. "[Learn more](https://…)" → "Learn more".
     */
    private function plainDescription(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1', $raw) ?? $raw;
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        return $text !== '' ? mb_substr($text, 0, 400) : null;
    }

    /**
     * Reshape one strategy's payload to exactly the fields the UI + recommender consume.
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function normalizeOne(array $r): array
    {
        $cwv = is_array($r['core_web_vitals'] ?? null) ? $r['core_web_vitals'] : [];

        return [
            'performance_score' => $this->intOrNull($r['performance_score'] ?? null),
            'lcp_ms' => $this->intOrNull($cwv['lcp_ms'] ?? null),
            'cls' => $this->floatOrNull($cwv['cls'] ?? null),
            'tbt_ms' => $this->intOrNull($cwv['tbt_ms'] ?? null),
            'fcp_ms' => $this->intOrNull($cwv['fcp_ms'] ?? null),
            'ttfb_ms' => $this->intOrNull($cwv['ttfb_ms'] ?? null),
            'speed_index_ms' => $this->intOrNull($cwv['speed_index_ms'] ?? null),
            'lighthouse_version' => is_string($r['lighthouse_version'] ?? null) ? $r['lighthouse_version'] : null,
            'runtime_error' => is_string($r['runtime_error'] ?? null) ? $r['runtime_error'] : null,
        ];
    }

    private function intOrNull(mixed $v): ?int
    {
        return is_numeric($v) ? (int) round((float) $v) : null;
    }

    private function floatOrNull(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }
}
