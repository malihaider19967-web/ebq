<?php

namespace App\Livewire\Keywords;

use App\Livewire\Keywords\Concerns\TracksKeyword;
use App\Models\KeywordApiRequest;
use App\Services\KeywordFinder\KeywordFinderPool;
use App\Services\KeywordFinder\KeywordIdeasMonthlyCache;
use App\Support\KeywordFinderLocations;
use App\Support\KeywordProviderConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * In-portal keyword discovery, powered by the self-hosted Keyword Planner API.
 * Two modes: expand seed keywords, or derive keywords from a website/page URL.
 *
 * The provider is asynchronous: {@see run} dispatches via {@see KeywordFinderPool}
 * (which creates a {@see KeywordApiRequest}), then the view polls {@see poll}
 * until the server posts results back to the webhook and the row completes.
 */
class KeywordIdeaFinder extends Component
{
    use TracksKeyword;

    /** Handoff payload from the research hub: {keywords: string[], mode?: string}. */
    public ?array $preset = null;

    /** 'seeds' | 'website' */
    public string $mode = 'seeds';

    /** Newline/comma-separated seed keywords. */
    public string $seedsInput = '';

    public string $url = '';

    /** 'site' | 'page' */
    public string $scope = 'site';

    public string $location = 'United States';

    public string $language = 'English';

    public ?string $requestId = null;

    public string $status = '';

    /** @var list<array<string, mixed>> */
    public array $results = [];

    public ?string $errorMessage = null;

    public bool $hasRun = false;

    /** True when the current results came straight from the monthly shared cache. */
    public bool $fromCache = false;

    /**
     * Set by run() right before dispatching, consumed by poll() once the
     * server's result lands — the monthly cache key this lookup should warm
     * on success. Null whenever this run was itself served from cache (no
     * dispatch happened, nothing new to write back).
     */
    public ?string $pendingCacheKey = null;

    // ── Results table state (sort / filter / paginate) ──────────────────────
    /** Sort column: keyword | volume | competitionIndex | cpc. */
    public string $sortField = 'volume';

    /** asc | desc */
    public string $sortDir = 'desc';

    /** Free-text "keyword contains" filter. */
    public string $filterText = '';

    public ?int $minVolume = null;

    public ?int $maxVolume = null;

    /** Competition filter: all | low | medium | high. */
    public string $comp = 'all';

    public int $perPage = 25;

    public int $page = 1;

    private const MAX_SEEDS = 20;

    /** Prefill + auto-run from a research-hub handoff (seed keywords). */
    public function mount(): void
    {
        $seeds = $this->preset['keywords'] ?? [];
        if (is_array($seeds) && $seeds !== []) {
            $this->mode = 'seeds';
            $this->seedsInput = implode("\n", $seeds);
            $this->run(app(KeywordFinderPool::class));
        }
    }

    /** Hand the keyword off to the Volume tab (research hub). */
    public function sendToVolume(string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $this->dispatch('research-handoff', target: 'volume', keywords: [$keyword]);
        }
    }

    /** Reset to the first page whenever a filter/page-size changes. */
    public function updated(string $name): void
    {
        if (in_array($name, ['filterText', 'minVolume', 'maxVolume', 'comp', 'perPage'], true)) {
            $this->page = 1;
        }
    }

    /** Toggle/choose the sort column (numeric columns default to descending). */
    public function sortBy(string $field): void
    {
        if (! in_array($field, ['keyword', 'volume', 'competitionIndex', 'cpc'], true)) {
            return;
        }
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = $field === 'keyword' ? 'asc' : 'desc';
        }
        $this->page = 1;
    }

    public function setPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function run(KeywordFinderPool $pool): void
    {
        $this->reset(['requestId', 'status', 'results', 'errorMessage', 'page', 'pendingCacheKey']);
        $this->hasRun = false;
        $this->fromCache = false;

        if (! KeywordProviderConfig::usingKeywordFinder()) {
            $this->errorMessage = 'Keyword discovery requires the self-hosted Keyword Planner provider, which is not currently enabled.';

            return;
        }

        $opts = [
            'location' => $this->location,
            'language' => $this->language,
        ];

        if ($this->mode === 'website') {
            $url = trim($this->url);
            if ($url === '') {
                $this->errorMessage = 'Enter a website or page URL.';

                return;
            }
            $opts['url'] = $url;
            $opts['scope'] = $this->scope === 'page' ? 'page' : 'site';
        } else {
            $seeds = $this->parseSeeds($this->seedsInput);
            if ($seeds === []) {
                $this->errorMessage = 'Enter at least one seed keyword.';

                return;
            }
            if (count($seeds) > self::MAX_SEEDS) {
                $this->errorMessage = 'Please enter at most '.self::MAX_SEEDS.' seed keywords.';

                return;
            }
            $opts['seeds'] = $seeds;
        }

        // Same seeds/URL + location/language, looked up by anyone this
        // calendar month, get the same answer instantly — no queue, no node
        // load, no wait. The key embeds Y-m, so it's automatically a miss
        // again once the month turns over.
        [$mode, $normalizedPayload] = $pool->buildIdeasPayload($opts);
        $cacheKey = KeywordIdeasMonthlyCache::key($mode, $normalizedPayload);
        $cached = KeywordIdeasMonthlyCache::get($cacheKey);
        if ($cached !== null) {
            $this->results = $cached;
            $this->hasRun = true;
            $this->fromCache = true;
            $this->status = KeywordApiRequest::STATUS_COMPLETED;

            return;
        }

        $request = $pool->dispatchIdeas($opts, userId: Auth::id());
        $this->hasRun = true;
        $this->requestId = $request->request_id;
        $this->status = $request->status;
        $this->pendingCacheKey = $cacheKey;

        if ($request->status === KeywordApiRequest::STATUS_FAILED) {
            $this->errorMessage = $request->error;
        }
    }

    /** Polled by the view while a request is in flight. */
    public function poll(): void
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

            return;
        }

        $rows = $request->result['results'] ?? [];
        $this->results = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
        $this->requestId = null;

        // Warm the shared monthly cache so the next person who searches the
        // same seeds/URL this month gets this exact result, instantly.
        if ($this->pendingCacheKey !== null) {
            KeywordIdeasMonthlyCache::put($this->pendingCacheKey, $this->results);
            $this->pendingCacheKey = null;
        }
    }

    public function isPolling(): bool
    {
        return $this->requestId !== null
            && in_array($this->status, [KeywordApiRequest::STATUS_QUEUED, KeywordApiRequest::STATUS_RUNNING], true);
    }

    /** @return list<string> */
    private function parseSeeds(string $raw): array
    {
        $parts = preg_split('/[\n,]+/', $raw) ?: [];
        $seen = [];
        $out = [];
        foreach ($parts as $p) {
            $s = trim($p);
            if ($s === '') {
                continue;
            }
            $key = mb_strtolower($s);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $s;
        }

        return $out;
    }

    /**
     * Normalise a raw API row into a stable, sortable/filterable shape.
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function normalizeRow(array $r): array
    {
        $vol = isset($r['avgMonthlySearches']) && is_numeric($r['avgMonthlySearches']) ? (int) $r['avgMonthlySearches'] : null;
        $idx = isset($r['competitionIndex']) && is_numeric($r['competitionIndex']) ? (int) $r['competitionIndex'] : null;
        $low = isset($r['lowTopOfPageBid']) && is_numeric($r['lowTopOfPageBid']) ? (float) $r['lowTopOfPageBid'] : null;
        $high = isset($r['highTopOfPageBid']) && is_numeric($r['highTopOfPageBid']) ? (float) $r['highTopOfPageBid'] : null;
        $compStr = is_string($r['competition'] ?? null) && $r['competition'] !== '' ? $r['competition'] : null;
        $level = $compStr !== null
            ? strtolower($compStr)
            : ($idx === null ? 'unknown' : ($idx < 34 ? 'low' : ($idx < 67 ? 'medium' : 'high')));

        return [
            'keyword' => (string) ($r['keyword'] ?? ''),
            'volume' => $vol,
            'competitionIndex' => $idx,
            'competition' => $compStr ?? ucfirst($level),
            'comp_level' => $level,
            'low' => $low,
            'high' => $high,
            'cpc' => $high, // high top-of-page bid drives the "CPC" sort
        ];
    }

    /**
     * Apply the active filters + sort to the full result set.
     *
     * @return list<array<string, mixed>>
     */
    private function processedResults(): array
    {
        $rows = array_map(fn ($r) => $this->normalizeRow($r), array_filter($this->results, 'is_array'));

        $text = mb_strtolower(trim($this->filterText));
        $rows = array_values(array_filter($rows, function (array $r) use ($text): bool {
            if ($r['keyword'] === '') {
                return false;
            }
            if ($text !== '' && ! str_contains(mb_strtolower($r['keyword']), $text)) {
                return false;
            }
            if ($this->minVolume !== null && ($r['volume'] ?? 0) < $this->minVolume) {
                return false;
            }
            if ($this->maxVolume !== null && ($r['volume'] ?? 0) > $this->maxVolume) {
                return false;
            }
            if ($this->comp !== 'all' && $r['comp_level'] !== $this->comp) {
                return false;
            }

            return true;
        }));

        $field = $this->sortField;
        $dir = $this->sortDir === 'asc' ? 1 : -1;
        usort($rows, function (array $a, array $b) use ($field, $dir): int {
            if ($field === 'keyword') {
                return $dir * strcasecmp((string) $a['keyword'], (string) $b['keyword']);
            }

            return $dir * (($a[$field] ?? -1) <=> ($b[$field] ?? -1));
        });

        return $rows;
    }

    /** Stream the filtered+sorted results as a CSV download. */
    public function export()
    {
        $rows = $this->processedResults();
        $filename = 'keyword-ideas-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Keyword', 'Avg monthly searches', 'Competition', 'Competition index', 'Low top-of-page bid', 'High top-of-page bid']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['keyword'], $r['volume'], $r['competition'], $r['competitionIndex'], $r['low'], $r['high']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $processed = $this->processedResults();
        $total = count($processed);
        $totalPages = max(1, (int) ceil($total / max(1, $this->perPage)));
        $this->page = min(max(1, $this->page), $totalPages);
        $rows = array_slice($processed, ($this->page - 1) * $this->perPage, $this->perPage);

        return view('livewire.keywords.keyword-idea-finder', [
            'languageOptions' => KeywordFinderLocations::languageOptions(),
            'locationNames' => KeywordFinderLocations::locationNames(),
            'rows' => $rows,
            'totalResults' => $total,
            'totalPages' => $totalPages,
        ]);
    }
}
