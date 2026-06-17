<?php

namespace App\Livewire\Keywords;

use App\Livewire\Keywords\Concerns\TracksKeyword;
use App\Models\KeywordApiRequest;
use App\Models\KeywordMetric;
use App\Models\Website;
use App\Services\KeywordFinder\KeywordFinderPool;
use App\Services\KeywordMetricsService;
use App\Services\Usage\UsageMeter;
use App\Support\KeywordFinderLocations;
use App\Support\KeywordProviderConfig;
use App\Support\KeywordsEverywhereCountries;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * In-portal bulk keyword volume finder.
 *
 * Two backends (admin-selectable, see {@see KeywordProviderConfig}):
 *
 *  - Keywords Everywhere: synchronous, credit-billed, cache-first. Only
 *    uncached keywords are fetched and billed.
 *  - Self-hosted Keyword Planner: asynchronous. Cache-first; on a miss we run
 *    the IDEAS endpoint (seed expansion), which returns the searched terms PLUS
 *    many related keywords — the webhook caches every one, so future lookups
 *    for any of them are free. The user is shown the FILTERED subset relevant
 *    to their query while the wider set quietly warms the shared cache.
 *
 * Either way, freshly fetched volumes land in the same {@see KeywordMetric}
 * cache the GSC import, Keywords table and rank tracker read from.
 */
class KeywordVolumeFinder extends Component
{
    use TracksKeyword;

    /** Handoff payload from the research hub: {keywords: string[]}. */
    public ?array $preset = null;

    /** Newline/comma-separated keyword input. */
    public string $keywords = '';

    /** Country code for the Keywords Everywhere provider (8-code list). */
    public string $country = 'global';

    /** Free-text location for the self-hosted Keyword Planner provider. */
    public string $location = 'United States';

    /** Language name — only used by the self-hosted Keyword Planner provider. */
    public string $language = 'English';

    /** @var list<array<string, mixed>> */
    public array $results = [];

    public ?string $errorMessage = null;

    public bool $hasRun = false;

    /** Async (finder) request being polled, if any. */
    public ?string $requestId = null;

    public string $status = '';

    /** Safety cap on a single lookup, independent of plan quota. */
    private const MAX_KEYWORDS = 100;

    /** Prefill + auto-run from a research-hub handoff (cache-first; KE bills only uncached). */
    public function mount(): void
    {
        $kws = $this->preset['keywords'] ?? [];
        if (is_array($kws) && $kws !== []) {
            $this->keywords = implode("\n", $kws);
            $this->run(app(KeywordMetricsService::class), app(UsageMeter::class), app(KeywordFinderPool::class));
        }
    }

    /** Hand the keyword off to the Ideas tab (research hub) as a seed. */
    public function sendToIdeas(string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $this->dispatch('research-handoff', target: 'ideas', mode: 'seeds', keywords: [$keyword]);
        }
    }

    public function run(KeywordMetricsService $metrics, UsageMeter $meter, KeywordFinderPool $pool): void
    {
        $this->reset(['errorMessage', 'results', 'requestId', 'status']);
        $this->hasRun = false;

        $list = $this->parseKeywords($this->keywords);

        if ($list === []) {
            $this->errorMessage = 'Enter at least one keyword.';

            return;
        }
        if (count($list) > self::MAX_KEYWORDS) {
            $this->errorMessage = 'Please check at most '.self::MAX_KEYWORDS.' keywords at a time. You entered '.count($list).'.';

            return;
        }

        if (KeywordProviderConfig::usingKeywordFinder()) {
            // The self-hosted provider supports any Google Ads location and the
            // full language list — don't squeeze it through the 8-code Keywords
            // Everywhere normaliser.
            $this->runViaFinder($list, KeywordFinderLocations::cacheKey($this->location), $metrics, $pool);

            return;
        }

        $this->runViaKeywordsEverywhere($list, KeywordsEverywhereCountries::normalize($this->country), $metrics, $meter);
    }

    /**
     * Self-hosted async path. Cache-first; otherwise dispatch an ideas run and
     * begin polling. Results are filtered to the query on completion.
     *
     * @param  list<string>  $list
     */
    private function runViaFinder(array $list, string $country, KeywordMetricsService $metrics, KeywordFinderPool $pool): void
    {
        $have = $metrics->metricsForMany($list, $country);

        $missing = [];
        foreach ($list as $kw) {
            $row = $have[KeywordMetric::hashKeyword($kw)] ?? null;
            if ($row === null || ! $row->isFresh()) {
                $missing[] = $kw;
            }
        }

        // Everything is already cached and fresh — no API call needed.
        if ($missing === []) {
            $this->results = $this->displayFromCache($list, $have);
            $this->hasRun = true;

            return;
        }

        $website = ($wid = session('current_website_id')) ? Website::find($wid) : null;

        $request = $pool->dispatchIdeas(
            ['seeds' => $missing, 'location' => $this->location, 'language' => $this->language],
            userId: Auth::id(),
            websiteId: $website?->id,
            countryKey: $country,
        );

        if ($request->status === KeywordApiRequest::STATUS_FAILED) {
            $this->errorMessage = $request->error ?: 'The keyword service is unavailable right now. Please try again shortly.';
            $this->hasRun = true;

            return;
        }

        // In flight — the view will poll() until the webhook delivers results.
        $this->requestId = $request->request_id;
        $this->status = $request->status;
    }

    /**
     * Original Keywords Everywhere synchronous path — unchanged behaviour.
     *
     * @param  list<string>  $list
     */
    private function runViaKeywordsEverywhere(array $list, string $country, KeywordMetricsService $metrics, UsageMeter $meter): void
    {
        if (! is_string(config('services.keywords_everywhere.key')) || trim((string) config('services.keywords_everywhere.key')) === '') {
            $this->errorMessage = 'Keyword volume lookups aren’t configured yet. Contact your administrator.';

            return;
        }

        $user = Auth::user();
        $website = ($wid = session('current_website_id')) ? Website::find($wid) : null;
        $billedUser = $website?->owner ?? $user;

        // Cache-first: figure out which keywords actually need a paid fetch.
        $have = $metrics->metricsForMany($list, $country);
        $toFetch = [];
        foreach ($list as $kw) {
            $row = $have[KeywordMetric::hashKeyword($kw)] ?? null;
            if ($row === null || ! $row->isFresh()) {
                $toFetch[] = $kw;
            }
        }

        // Pre-flight the plan quota for ONLY the uncached keywords. Cached ones
        // never count, so re-checking the same list is always free.
        $remaining = $billedUser ? $meter->remaining($billedUser, 'keywords_everywhere') : null;
        if ($remaining !== null && count($toFetch) > $remaining) {
            $cached = count($list) - count($toFetch);
            $this->errorMessage = 'This lookup needs '.count($toFetch).' new credit'.(count($toFetch) === 1 ? '' : 's')
                .', but you have '.$remaining.' left this month'
                .($cached > 0 ? ' ('.$cached.' of your keywords '.($cached === 1 ? 'is' : 'are').' already cached and free)' : '')
                .'. Remove some keywords or upgrade your plan.';

            return;
        }

        if ($toFetch !== []) {
            $metrics->refresh($toFetch, $country, websiteId: $website?->id, ownerUserId: $billedUser?->id, source: 'portal_volume_finder');
        }

        // Re-read everything (now including freshly fetched rows) and build the
        // display set, flagging which were served from existing cache.
        $fresh = $metrics->metricsForMany($list, $country);
        $fetchedSet = array_fill_keys(array_map(fn ($k) => KeywordMetric::hashKeyword($k), $toFetch), true);

        foreach ($list as $kw) {
            $hash = KeywordMetric::hashKeyword($kw);
            $row = $fresh[$hash] ?? null;
            $this->results[] = [
                'keyword' => $kw,
                'volume' => $row?->search_volume,
                'cpc' => $row?->cpc,
                'currency' => $row?->currency ?: 'USD',
                'competition' => $row?->competition,
                'trend' => is_array($row?->trend_12m) ? $row->trend_12m : [],
                'from_cache' => ! isset($fetchedSet[$hash]),
            ];
        }

        $this->sortByVolume($this->results);
        $this->hasRun = true;
    }

    /** Polled by the view while a finder request is in flight. */
    public function poll(KeywordMetricsService $metrics): void
    {
        if ($this->requestId === null) {
            return;
        }

        $request = KeywordApiRequest::query()->where('request_id', $this->requestId)->first();
        if ($request === null) {
            return;
        }

        $this->status = $request->status;
        if (! $request->isFinished()) {
            return;
        }

        if ($request->status === KeywordApiRequest::STATUS_FAILED) {
            $this->errorMessage = $request->error ?: 'The lookup failed. Please try again.';
            $this->requestId = null;
            $this->hasRun = true;

            return;
        }

        // Completed — the webhook has cached every returned keyword. The volume
        // tool only shows the keywords the user actually searched (the wider
        // ideas set quietly warmed the shared cache; it's the Keyword Discovery
        // page's job to surface related ideas). Re-read just the searched terms.
        $country = KeywordFinderLocations::cacheKey($this->location);
        $seeds = $this->parseKeywords($this->keywords);

        $this->results = $this->displayFromCache($seeds, $metrics->metricsForMany($seeds, $country));
        $this->requestId = null;
        $this->hasRun = true;
    }

    public function isPolling(): bool
    {
        return $this->requestId !== null
            && in_array($this->status, [KeywordApiRequest::STATUS_QUEUED, KeywordApiRequest::STATUS_RUNNING], true);
    }

    /**
     * Build a display set straight from fresh cache rows (finder all-cached or
     * KE re-check).
     *
     * @param  list<string>  $list
     * @param  array<string, KeywordMetric>  $have
     * @return list<array<string, mixed>>
     */
    private function displayFromCache(array $list, array $have): array
    {
        $out = [];
        foreach ($list as $kw) {
            $row = $have[KeywordMetric::hashKeyword($kw)] ?? null;
            $out[] = [
                'keyword' => $kw,
                'volume' => $row?->search_volume,
                'cpc' => $row?->cpc,
                'currency' => $row?->currency ?: 'USD',
                'competition' => $row?->competition,
                'trend' => is_array($row?->trend_12m) ? $row->trend_12m : [],
                'from_cache' => true,
            ];
        }
        $this->sortByVolume($out);

        return $out;
    }

    /** Sort by volume desc, nulls last — most useful research order. */
    private function sortByVolume(array &$rows): void
    {
        usort($rows, fn ($a, $b) => ($b['volume'] ?? -1) <=> ($a['volume'] ?? -1));
    }

    /** @return list<string> unique, trimmed, case-insensitively de-duped. */
    private function parseKeywords(string $raw): array
    {
        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $seen = [];
        $out = [];
        foreach ($parts as $p) {
            $s = trim((string) $p);
            if ($s === '') {
                continue;
            }
            $key = mb_strtolower($s);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = mb_substr($s, 0, 200);
        }

        return $out;
    }

    public function render()
    {
        $user = Auth::user();
        $website = ($wid = session('current_website_id')) ? Website::find($wid) : null;
        $billedUser = $website?->owner ?? $user;
        $meter = app(UsageMeter::class);
        $usingFinder = KeywordProviderConfig::usingKeywordFinder();

        return view('livewire.keywords.keyword-volume-finder', [
            'countries' => KeywordsEverywhereCountries::options(),
            'locationNames' => KeywordFinderLocations::locationNames(),
            'languages' => KeywordFinderLocations::languageOptions(),
            'usingFinder' => $usingFinder,
            'remaining' => (! $usingFinder && $billedUser) ? $meter->remaining($billedUser, 'keywords_everywhere') : null,
            'limit' => (! $usingFinder && $billedUser) ? $meter->limit($billedUser, 'keywords_everywhere') : null,
        ]);
    }
}
