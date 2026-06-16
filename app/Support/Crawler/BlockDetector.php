<?php

namespace App\Support\Crawler;

/**
 * Classifies whether a fetch response indicates the crawler is being blocked
 * (bot-wall / CAPTCHA / rate-limit / login wall) rather than a genuine page
 * problem. Used per-response and rolled up per-run so a blocked site is never
 * reported as an empty/healthy one.
 */
class BlockDetector
{
    public const BLOCKED = 'blocked';
    public const CAPTCHA = 'captcha';
    public const RATE_LIMITED = 'rate_limited';
    public const LOGIN_REQUIRED = 'login_required';

    /**
     * Full-page bot-walls: the whole response IS the block. Reliable on their own.
     */
    private const WALL_FINGERPRINTS = [
        'just a moment', 'checking your browser', 'cf-browser-verification',
        'cf_chl_opt', '/cdn-cgi/challenge-platform', 'are you a robot', 'are you human',
        'verify you are human', 'datadome', 'px-captcha', 'perimeterx',
        'access denied', 'attention required', 'incapsula', '_imperva_',
        'request unsuccessful. incapsula',
    ];

    /**
     * Captcha widgets that ALSO appear on perfectly normal content pages (contact
     * and login forms embed reCAPTCHA/hCaptcha). These count as a block only when
     * the page is essentially just the widget (almost no real content) — otherwise
     * we fetched the real page fine and it merely contains a captcha element.
     */
    private const WIDGET_FINGERPRINTS = ['recaptcha', 'g-recaptcha', 'hcaptcha', 'h-captcha'];

    /** A captcha widget on a page with fewer visible-text chars than this is a wall. */
    private const WALL_TEXT_MAX = 1000;

    /**
     * Classify a single fetch result. Returns a reason constant or null.
     *
     * @param  array{status:?int,body:string,headers?:array<string,string>}  $res
     */
    public function classify(array $res): ?string
    {
        $status = $res['status'] ?? null;

        if ($status === 429) {
            return self::RATE_LIMITED;
        }
        if ($status === 401) {
            return self::LOGIN_REQUIRED;
        }

        $rawBody = (string) ($res['body'] ?? '');
        $body = strtolower(substr($rawBody, 0, 20_000));

        // Full-page bot-walls always count.
        foreach (self::WALL_FINGERPRINTS as $needle) {
            if ($body !== '' && str_contains($body, $needle)) {
                return self::CAPTCHA;
            }
        }

        $headers = $res['headers'] ?? [];
        if (isset($headers['cf-mitigated']) && stripos((string) $headers['cf-mitigated'], 'challenge') !== false) {
            return self::CAPTCHA;
        }

        // A captcha widget only means "blocked" when the page is basically just the
        // widget — a real content page that embeds reCAPTCHA (contact form) is fine.
        foreach (self::WIDGET_FINGERPRINTS as $needle) {
            if ($body !== '' && str_contains($body, $needle)) {
                return $this->visibleTextLength($rawBody) < self::WALL_TEXT_MAX ? self::CAPTCHA : null;
            }
        }

        if ($status === 403) {
            return self::BLOCKED;
        }

        return null;
    }

    /** Length of visible page text (tags + script/style stripped). */
    private function visibleTextLength(string $html): int
    {
        $html = substr($html, 0, 200_000);
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = preg_replace('/\s+/', ' ', strip_tags($html)) ?? '';

        return mb_strlen(trim($text));
    }

    /**
     * Roll up per-page outcomes into a site-wide block verdict.
     *
     * @param  array<string,int>  $reasonCounts  reason => count of pages flagged
     * @param  int  $fetched  total pages we attempted to fetch
     * @return array{blocked: bool, reason: ?string}
     */
    public function rollup(array $reasonCounts, int $fetched): array
    {
        $flagged = array_sum($reasonCounts);
        if ($fetched <= 0 || $flagged === 0) {
            return ['blocked' => false, 'reason' => null];
        }

        // Wholesale block when the majority of fetched pages are challenged.
        if ($flagged / max(1, $fetched) >= 0.6) {
            arsort($reasonCounts);
            $reason = (string) array_key_first($reasonCounts);

            return ['blocked' => true, 'reason' => $reason];
        }

        return ['blocked' => false, 'reason' => null];
    }
}
