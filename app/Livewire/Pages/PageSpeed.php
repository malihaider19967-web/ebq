<?php

namespace App\Livewire\Pages;

use App\Jobs\RunPageSpeedStrategy;
use App\Services\LighthouseClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Standalone PageSpeed Insights tool. Runs a full mobile + desktop Lighthouse
 * report (performance, accessibility, best-practices, SEO) on the self-hosted
 * service.
 *
 * The measurement is ASYNCHRONOUS: each strategy runs as its own queued job
 * (RunPageSpeedStrategy) and stashes its result in the cache; this component
 * polls until both land. A full run can exceed a minute on heavy sites — far
 * past Cloudflare's proxy timeout — so it must never block the web request.
 */
class PageSpeed extends Component
{
    #[Url(as: 'url')]
    public string $url = '';

    /** idle | running | done */
    public string $status = 'idle';

    public string $runId = '';

    public ?int $startedAt = null;

    /** Per-strategy live status while running: running | done | failed. */
    public array $progress = ['mobile' => 'running', 'desktop' => 'running'];

    /** @var array<string, mixed>|null Assembled mobile+desktop report once both strategies finish. */
    public ?array $result = null;

    public ?string $testedUrl = null;

    public ?string $errorMessage = null;

    /** Give up polling after this many seconds (covers a stuck/queueless worker). */
    private const MAX_WAIT_SECONDS = 240;

    /**
     * Kick off a background mobile + desktop measurement.
     */
    public function runTest(LighthouseClient $lighthouse): void
    {
        $this->reset(['result', 'errorMessage', 'testedUrl', 'runId', 'startedAt', 'progress']);
        $this->status = 'idle';

        $user = Auth::user();
        if (! $user) {
            $this->errorMessage = 'You must be signed in to run a PageSpeed test.';

            return;
        }

        $normalizedUrl = $this->normalizeUrl($this->url);

        Validator::make(
            ['url' => $normalizedUrl],
            ['url' => ['required', 'string', 'max:2000', 'url']],
            [
                'url.required' => 'Enter a page URL to test.',
                'url.url' => 'Enter a valid URL, e.g. https://example.com/page.',
            ],
            [],
            ['url' => 'page URL'],
        )->validate();

        if (! $lighthouse->isConfigured()) {
            $this->errorMessage = 'PageSpeed testing is temporarily unavailable. Please try again later.';

            return;
        }

        $rateKey = 'pagespeed-test:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $this->errorMessage = "Too many tests. Try again in {$seconds}s.";

            return;
        }
        RateLimiter::hit($rateKey, 300);

        $this->runId = (string) Str::uuid();
        $this->testedUrl = $normalizedUrl;
        $this->startedAt = now()->timestamp;
        $this->status = 'running';

        // One short job per strategy — the two ebq workers can run them in
        // parallel, each well under the worker's 90s timeout.
        RunPageSpeedStrategy::dispatch($this->runId, $normalizedUrl, 'mobile');
        RunPageSpeedStrategy::dispatch($this->runId, $normalizedUrl, 'desktop');
    }

    /**
     * Polled from the view while status === running. Assembles the report
     * once both strategies report in (or one fails / we time out).
     */
    public function pollResult(): void
    {
        if ($this->status !== 'running' || $this->runId === '') {
            return;
        }

        $mobileRaw = Cache::get(RunPageSpeedStrategy::keyFor($this->runId, 'mobile'));
        $desktopRaw = Cache::get(RunPageSpeedStrategy::keyFor($this->runId, 'desktop'));

        // Surface live per-strategy progress so the UI can show which
        // device is still being measured.
        $this->progress = [
            'mobile' => $this->progressFor($mobileRaw),
            'desktop' => $this->progressFor($desktopRaw),
        ];

        $bothIn = $mobileRaw !== null && $desktopRaw !== null;
        $timedOut = $this->startedAt !== null && (now()->timestamp - $this->startedAt) > self::MAX_WAIT_SECONDS;

        if (! $bothIn && ! $timedOut) {
            return; // keep polling
        }

        $mobile = $this->strategyOrNull($mobileRaw);
        $desktop = $this->strategyOrNull($desktopRaw);

        // Clean up so a re-run with a new id doesn't collide.
        Cache::forget(RunPageSpeedStrategy::keyFor($this->runId, 'mobile'));
        Cache::forget(RunPageSpeedStrategy::keyFor($this->runId, 'desktop'));

        $this->status = 'done';

        if ($mobile === null && $desktop === null) {
            $this->errorMessage = $timedOut
                ? 'The measurement timed out. The site may be slow or unreachable — try again.'
                : 'Could not measure that URL. Check that it is publicly reachable and try again.';

            return;
        }

        $this->result = [
            'mobile' => $mobile,
            'desktop' => $desktop,
            'fetched_at' => now()->toIso8601String(),
            'lighthouse_version' => $mobile['lighthouse_version'] ?? $desktop['lighthouse_version'] ?? null,
            'source' => 'lighthouse-local',
        ];
    }

    /**
     * @param  mixed  $raw
     * @return array<string, mixed>|null
     */
    private function strategyOrNull(mixed $raw): ?array
    {
        if (! is_array($raw) || ($raw['error'] ?? false)) {
            return null;
        }

        return $raw;
    }

    /**
     * Live status of one strategy from its cache slot.
     */
    private function progressFor(mixed $raw): string
    {
        if ($raw === null) {
            return 'running';
        }

        return $this->strategyOrNull($raw) !== null ? 'done' : 'failed';
    }

    private function normalizeUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $raw)) {
            $raw = 'https://'.$raw;
        }

        return $raw;
    }

    public function render()
    {
        return view('livewire.pages.page-speed');
    }
}
