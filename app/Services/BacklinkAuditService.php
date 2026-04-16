<?php

namespace App\Services;

use App\Models\Backlink;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class BacklinkAuditService
{
    public const STATUS_MATCHED = 'matched';

    public const STATUS_MISMATCHED = 'mismatched';

    public const STATUS_MISSING = 'missing';

    public const STATUS_UNREACHABLE = 'unreachable';

    public function audit(Backlink $backlink): Backlink
    {
        $result = $this->runAudit($backlink);

        $backlink->forceFill([
            'audit_status' => $result['status'],
            'audit_checked_at' => Carbon::now(),
            'audit_result' => $result,
        ])->save();

        return $backlink;
    }

    /**
     * @return array<string, mixed>
     */
    private function runAudit(Backlink $backlink): array
    {
        $fetched = $this->fetch($backlink->referring_page_url);

        if (! $fetched['ok']) {
            return [
                'status' => self::STATUS_UNREACHABLE,
                'http_status' => $fetched['status'],
                'message' => $fetched['message'],
                'link_present' => false,
                'matches' => [],
                'mismatches' => [],
                'found_links' => [],
            ];
        }

        $links = $this->extractMatchingLinks($fetched['body'], $backlink->target_page_url);

        if ($links === []) {
            return [
                'status' => self::STATUS_MISSING,
                'http_status' => $fetched['status'],
                'message' => 'No link to the target URL was found on the referring page.',
                'link_present' => false,
                'matches' => [],
                'mismatches' => [
                    [
                        'field' => 'link_present',
                        'expected' => true,
                        'actual' => false,
                    ],
                ],
                'found_links' => [],
            ];
        }

        $expectedAnchor = trim((string) $backlink->anchor_text);
        $expectedDofollow = (bool) $backlink->is_dofollow;

        $best = $this->pickBestLink($links, $expectedAnchor, $expectedDofollow);

        $checks = [
            [
                'field' => 'link_present',
                'expected' => true,
                'actual' => true,
            ],
            [
                'field' => 'anchor_text',
                'expected' => $expectedAnchor !== '' ? $expectedAnchor : null,
                'actual' => $best['anchor'],
            ],
            [
                'field' => 'is_dofollow',
                'expected' => $expectedDofollow,
                'actual' => $best['is_dofollow'],
            ],
        ];

        $matches = [];
        $mismatches = [];
        foreach ($checks as $check) {
            if ($this->isMatch($check['field'], $check['expected'], $check['actual'])) {
                $matches[] = $check;
            } else {
                $mismatches[] = $check;
            }
        }

        return [
            'status' => $mismatches === [] ? self::STATUS_MATCHED : self::STATUS_MISMATCHED,
            'http_status' => $fetched['status'],
            'message' => null,
            'link_present' => true,
            'matches' => $matches,
            'mismatches' => $mismatches,
            'found_links' => $links,
        ];
    }

    /**
     * @return array{ok: bool, status: ?int, body: string, message: ?string}
     */
    private function fetch(string $url): array
    {
        try {
            $response = Http::timeout(15)
                ->connectTimeout(10)
                ->withUserAgent('Mozilla/5.0 (compatible; BacklinkAuditor/1.0)')
                ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
                ->withOptions(['allow_redirects' => true])
                ->get($url);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'body' => '',
                    'message' => 'HTTP '.$response->status().' from referring page.',
                ];
            }

            return [
                'ok' => true,
                'status' => $response->status(),
                'body' => (string) $response->body(),
                'message' => null,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'body' => '',
                'message' => 'Could not fetch referring page: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return list<array{href: string, anchor: string, rel: string, is_dofollow: bool}>
     */
    private function extractMatchingLinks(string $html, string $target): array
    {
        if ($html === '') {
            return [];
        }

        $normTarget = $this->normalizeUrl($target);

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $matches = [];
        foreach ($doc->getElementsByTagName('a') as $a) {
            if (! $a instanceof DOMElement) {
                continue;
            }
            $href = trim((string) $a->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            if ($this->normalizeUrl($href) !== $normTarget) {
                continue;
            }

            $rel = trim((string) $a->getAttribute('rel'));
            $anchor = trim(preg_replace('/\s+/', ' ', (string) $a->textContent));

            $matches[] = [
                'href' => $href,
                'anchor' => $anchor,
                'rel' => $rel,
                'is_dofollow' => $this->isDofollow($rel),
            ];
        }

        return $matches;
    }

    /**
     * @param  list<array{href: string, anchor: string, rel: string, is_dofollow: bool}>  $links
     * @return array{href: string, anchor: string, rel: string, is_dofollow: bool}
     */
    private function pickBestLink(array $links, string $expectedAnchor, bool $expectedDofollow): array
    {
        $expectedAnchorNorm = mb_strtolower($expectedAnchor);

        $best = $links[0];
        $bestScore = -1;
        foreach ($links as $link) {
            $score = 0;
            if ($expectedAnchor !== '' && mb_strtolower($link['anchor']) === $expectedAnchorNorm) {
                $score += 2;
            }
            if ($link['is_dofollow'] === $expectedDofollow) {
                $score += 1;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $link;
            }
        }

        return $best;
    }

    private function isMatch(string $field, mixed $expected, mixed $actual): bool
    {
        if ($field === 'anchor_text') {
            $e = mb_strtolower(trim((string) $expected));
            $a = mb_strtolower(trim((string) $actual));

            return $e === '' ? true : $e === $a;
        }

        return $expected === $actual;
    }

    private function isDofollow(string $rel): bool
    {
        if ($rel === '') {
            return true;
        }
        $tokens = preg_split('/\s+/', mb_strtolower($rel)) ?: [];

        foreach (['nofollow', 'ugc', 'sponsored'] as $bad) {
            if (in_array($bad, $tokens, true)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = @parse_url($url);
        if ($parts === false || $parts === null) {
            return mb_strtolower($url);
        }

        $scheme = mb_strtolower($parts['scheme'] ?? 'https');
        $host = mb_strtolower($parts['host'] ?? '');
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = $parts['path'] ?? '';
        if ($path === '') {
            $path = '/';
        }
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }
        $query = $parts['query'] ?? '';
        $query = $this->stripTrackingParams($query);

        $out = $scheme.'://'.$host.$path;
        if ($query !== '') {
            $out .= '?'.$query;
        }

        return $out;
    }

    private function stripTrackingParams(string $query): string
    {
        if ($query === '') {
            return '';
        }
        parse_str($query, $params);
        $kept = [];
        foreach ($params as $k => $v) {
            if (str_starts_with(mb_strtolower((string) $k), 'utm_')) {
                continue;
            }
            $kept[$k] = $v;
        }
        if ($kept === []) {
            return '';
        }
        ksort($kept);

        return http_build_query($kept);
    }
}
