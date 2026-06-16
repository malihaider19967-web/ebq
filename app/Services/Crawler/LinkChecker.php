<?php

namespace App\Services\Crawler;

use App\Support\Audit\SafeHttpGuard;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Concurrent link-status checker. Mirrors PageAuditService::checkLinks() (HEAD
 * with GET fallback for 403/405/501, SSRF re-guard, pooled concurrency) but
 * returns full per-link results — broken AND redirected — so the crawler's
 * issue detector can classify (broken_link vs redirect_chain).
 */
class LinkChecker
{
    private const TIMEOUT = 8;
    private const CONCURRENCY = 10;

    public function __construct(private readonly SafeHttpGuard $guard) {}

    /**
     * @param  array<int,array{href:string,anchor?:string}>  $links
     * @return array<int,array{href:string,anchor:string,status:?int,error:?string,redirected:bool,final_url:?string,chain:int}>
     *         Only problematic links (status>=400, transport error, or redirected) are returned.
     */
    public function check(array $links, int $max = 200): array
    {
        if ($links === []) {
            return [];
        }

        $unique = [];
        foreach ($links as $l) {
            $href = (string) ($l['href'] ?? '');
            if ($href === '' || isset($unique[$href])) {
                continue;
            }
            $unique[$href] = ['href' => $href, 'anchor' => (string) ($l['anchor'] ?? '')];
            if (count($unique) >= $max) {
                break;
            }
        }

        $problems = [];
        $toCheck = [];
        foreach ($unique as $link) {
            $check = $this->guard->check($link['href']);
            if (! $check['ok']) {
                // Mailto/tel/relative would have been filtered upstream; a guard
                // failure here means a genuinely unfetchable/unsafe target.
                $problems[] = $this->row($link, null, $check['reason'] ?? 'blocked', false, null, 0);

                continue;
            }
            $toCheck[] = $link;
        }

        foreach (array_chunk($toCheck, self::CONCURRENCY) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch) {
                $calls = [];
                foreach ($batch as $i => $link) {
                    $calls[] = $pool->as((string) $i)
                        ->timeout(self::TIMEOUT)
                        ->connectTimeout(self::TIMEOUT)
                        ->withUserAgent(CrawlFetcher::UA)
                        ->withOptions([
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
                        ->head($link['href']);
                }

                return $calls;
            });

            foreach ($batch as $i => $link) {
                $resp = $responses[(string) $i] ?? null;
                $status = null;
                $error = null;
                $redirected = false;
                $finalUrl = null;
                $chain = 0;

                if ($resp instanceof Response) {
                    $status = $resp->status();
                    if (in_array($status, [403, 405, 501], true)) {
                        $status = $this->getFallback($link['href']) ?? $status;
                    }
                    $history = array_filter(array_map('trim', explode(',', (string) $resp->header('X-Guzzle-Redirect-History'))));
                    $chain = count($history);
                    $redirected = $chain > 0;
                    $finalUrl = $redirected ? (string) end($history) : null;
                } else {
                    $error = $resp instanceof \Throwable ? $resp->getMessage() : 'unknown';
                }

                if ($status === null || $status >= 400 || $redirected) {
                    $problems[] = $this->row($link, $status, $error, $redirected, $finalUrl, $chain);
                }
            }
        }

        return $problems;
    }

    private function getFallback(string $url): ?int
    {
        try {
            return Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::TIMEOUT)
                ->withUserAgent(CrawlFetcher::UA)
                ->get($url)
                ->status();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array{href:string,anchor:string}  $link
     * @return array{href:string,anchor:string,status:?int,error:?string,redirected:bool,final_url:?string,chain:int}
     */
    private function row(array $link, ?int $status, ?string $error, bool $redirected, ?string $finalUrl, int $chain): array
    {
        return [
            'href' => $link['href'],
            'anchor' => $link['anchor'],
            'status' => $status,
            'error' => $error,
            'redirected' => $redirected,
            'final_url' => $finalUrl,
            'chain' => $chain,
        ];
    }
}
