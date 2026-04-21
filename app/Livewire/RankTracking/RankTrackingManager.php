<?php

namespace App\Livewire\RankTracking;

use App\Jobs\TrackKeywordRankJob;
use App\Models\RankTrackingKeyword;
use App\Models\SearchConsoleData;
use App\Models\Website;
use App\Services\SerpFeatureRiskService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class RankTrackingManager extends Component
{
    use WithPagination;

    public int $websiteId = 0;

    public bool $showForm = false;
    public string $search = '';
    public string $sortBy = 'current_position';
    public string $sortDir = 'asc';
    public string $filterDevice = '';
    public string $filterCountry = '';
    public string $filterType = '';
    public string $filterStatus = '';

    public string $newKeyword = '';
    public string $newTargetDomain = '';
    public string $newTargetUrl = '';
    public string $newSearchEngine = 'google';
    public string $newSearchType = 'organic';
    public string $newCountry = 'us';
    public string $newLanguage = 'en';
    public string $newLocation = '';
    public string $newDevice = 'desktop';
    public int $newDepth = 100;
    public string $newTbs = '';
    public bool $newAutocorrect = true;
    public bool $newSafeSearch = false;
    public string $newCompetitors = '';
    public string $newTags = '';
    public string $newNotes = '';
    public int $newIntervalHours = 12;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
        $this->prefillTargetDomain();
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        $this->prefillTargetDomain();
        $this->resetPage();
    }

    private function prefillTargetDomain(): void
    {
        if ($this->websiteId <= 0) {
            return;
        }
        $website = Website::find($this->websiteId);
        if ($website && (string) $website->domain !== '') {
            $this->newTargetDomain = (string) $website->domain;
        }
    }

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDevice(): void { $this->resetPage(); }
    public function updatedFilterCountry(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterDevice', 'filterCountry', 'filterType', 'filterStatus']);
        $this->resetPage();
    }

    public function addKeyword(): void
    {
        $this->validate([
            'newKeyword' => 'required|string|min:1|max:500',
            'newTargetDomain' => 'required|string|max:255',
            'newTargetUrl' => 'nullable|string|max:2048',
            'newSearchEngine' => 'required|in:google',
            'newSearchType' => 'required|in:organic,news,images,videos,shopping,maps,scholar',
            'newCountry' => 'required|string|size:2',
            'newLanguage' => 'required|string|min:2|max:10',
            'newLocation' => 'nullable|string|max:255',
            'newDevice' => 'required|in:desktop,mobile',
            'newDepth' => 'required|integer|min:10|max:100',
            'newTbs' => 'nullable|string|max:64',
            'newIntervalHours' => 'required|integer|min:1|max:168',
        ]);

        $user = Auth::user();
        if (! $user || $this->websiteId <= 0 || ! $user->canViewWebsiteId($this->websiteId)) {
            $this->addError('newKeyword', 'Select a website first.');

            return;
        }

        $competitors = collect(explode(',', $this->newCompetitors))
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values()
            ->all();

        $tags = collect(explode(',', $this->newTags))
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values()
            ->all();

        $keyword = RankTrackingKeyword::updateOrCreate(
            [
                'website_id' => $this->websiteId,
                'keyword_hash' => RankTrackingKeyword::hashKeyword($this->newKeyword),
                'search_engine' => $this->newSearchEngine,
                'search_type' => $this->newSearchType,
                'country' => strtolower($this->newCountry),
                'language' => strtolower($this->newLanguage),
                'device' => $this->newDevice,
                'location' => $this->newLocation ?: null,
            ],
            [
                'user_id' => $user->id,
                'keyword' => trim($this->newKeyword),
                'target_domain' => trim($this->newTargetDomain),
                'target_url' => $this->newTargetUrl ?: null,
                'depth' => $this->newDepth,
                'tbs' => $this->newTbs ?: null,
                'autocorrect' => $this->newAutocorrect,
                'safe_search' => $this->newSafeSearch,
                'competitors' => $competitors,
                'tags' => $tags,
                'notes' => $this->newNotes ?: null,
                'check_interval_hours' => $this->newIntervalHours,
                'is_active' => true,
                'next_check_at' => Carbon::now(),
            ]
        );

        TrackKeywordRankJob::dispatch($keyword->id, true);

        $this->reset([
            'newKeyword',
            'newTargetUrl',
            'newLocation',
            'newTbs',
            'newCompetitors',
            'newTags',
            'newNotes',
            'showForm',
        ]);

        session()->flash('rank_tracking_status', 'Keyword added and initial check queued.');
    }

    public function recheck(int $keywordId): void
    {
        $keyword = RankTrackingKeyword::find($keywordId);
        if (! $keyword || ! $this->authorizes($keyword)) {
            return;
        }

        TrackKeywordRankJob::dispatch($keyword->id, true);

        $keyword->forceFill([
            'last_status' => 'queued',
            'next_check_at' => Carbon::now(),
        ])->save();

        session()->flash('rank_tracking_status', 'Re-check queued for "'.$keyword->keyword.'".');
    }

    public function togglePause(int $keywordId): void
    {
        $keyword = RankTrackingKeyword::find($keywordId);
        if (! $keyword || ! $this->authorizes($keyword)) {
            return;
        }

        $keyword->is_active = ! $keyword->is_active;
        $keyword->save();
    }

    public function delete(int $keywordId): void
    {
        $keyword = RankTrackingKeyword::find($keywordId);
        if (! $keyword || ! $this->authorizes($keyword)) {
            return;
        }
        $keyword->delete();
    }

    private function authorizes(RankTrackingKeyword $keyword): bool
    {
        $user = Auth::user();

        return $user !== null && $user->canViewWebsiteId((int) $keyword->website_id);
    }

    public function render()
    {
        $rows = collect();
        $stats = [
            'total' => 0,
            'top3' => 0,
            'top10' => 0,
            'top100' => 0,
            'avg' => null,
            'unranked' => 0,
            'active' => 0,
        ];

        $allowed = ['keyword', 'current_position', 'best_position', 'position_change', 'last_checked_at', 'country', 'device'];
        $sortBy = in_array($this->sortBy, $allowed, true) ? $this->sortBy : 'current_position';

        if ($this->websiteId && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $base = RankTrackingKeyword::query()
                ->where('website_id', $this->websiteId);

            $stats['total'] = (clone $base)->count();
            $stats['active'] = (clone $base)->where('is_active', true)->count();
            $stats['top3'] = (clone $base)->whereBetween('current_position', [1, 3])->count();
            $stats['top10'] = (clone $base)->whereBetween('current_position', [1, 10])->count();
            $stats['top100'] = (clone $base)->whereNotNull('current_position')->count();
            $stats['unranked'] = (clone $base)->whereNull('current_position')->count();
            $avgVal = (clone $base)->whereNotNull('current_position')->avg('current_position');
            $stats['avg'] = $avgVal !== null ? round((float) $avgVal, 1) : null;

            $filtered = (clone $base)
                ->when($this->search, fn ($q) => $q->where('keyword', 'like', '%'.$this->search.'%'))
                ->when($this->filterDevice, fn ($q) => $q->where('device', $this->filterDevice))
                ->when($this->filterCountry, fn ($q) => $q->where('country', $this->filterCountry))
                ->when($this->filterType, fn ($q) => $q->where('search_type', $this->filterType))
                ->when($this->filterStatus === 'top3', fn ($q) => $q->whereBetween('current_position', [1, 3]))
                ->when($this->filterStatus === 'top10', fn ($q) => $q->whereBetween('current_position', [1, 10]))
                ->when($this->filterStatus === 'top100', fn ($q) => $q->whereNotNull('current_position'))
                ->when($this->filterStatus === 'unranked', fn ($q) => $q->whereNull('current_position'))
                ->when($this->filterStatus === 'active', fn ($q) => $q->where('is_active', true))
                ->when($this->filterStatus === 'paused', fn ($q) => $q->where('is_active', false))
                ->when($this->filterStatus === 'failed', fn ($q) => $q->where('last_status', 'failed'));

            $rows = $filtered
                ->orderByRaw("CASE WHEN {$sortBy} IS NULL THEN 1 ELSE 0 END")
                ->orderBy($sortBy, $this->sortDir)
                ->paginate(25);
        }

        $gscByKeyword = [];
        if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->isNotEmpty()) {
            $keywords = $rows->getCollection()->pluck('keyword')->map(fn ($k) => mb_strtolower(trim((string) $k)))->unique()->values()->all();
            $since = Carbon::now()->subDays(30)->toDateString();

            if (! empty($keywords) && $this->websiteId) {
                $gscRows = SearchConsoleData::query()
                    ->where('website_id', $this->websiteId)
                    ->whereDate('date', '>=', $since)
                    ->whereIn(DB::raw('LOWER(`query`)'), $keywords)
                    ->selectRaw('LOWER(`query`) as q, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position')
                    ->groupBy('q')
                    ->get();

                foreach ($gscRows as $g) {
                    $gscByKeyword[(string) $g->q] = [
                        'clicks' => (int) $g->clicks,
                        'impressions' => (int) $g->impressions,
                        'position' => $g->position !== null ? round((float) $g->position, 1) : null,
                    ];
                }
            }
        }

        $serpRisk = [];
        if ($this->websiteId && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $serpRisk = app(SerpFeatureRiskService::class)->riskMapForWebsite($this->websiteId);
        }

        return view('livewire.rank-tracking.rank-tracking-manager', [
            'rows' => $rows,
            'stats' => $stats,
            'gscByKeyword' => $gscByKeyword,
            'serpRisk' => $serpRisk,
            'countries' => $this->countries(),
            'languages' => $this->languages(),
        ]);
    }

    /** @return array<string, string> */
    private function countries(): array
    {
        return [
            'us' => 'United States', 'gb' => 'United Kingdom', 'ca' => 'Canada', 'au' => 'Australia',
            'de' => 'Germany', 'fr' => 'France', 'es' => 'Spain', 'it' => 'Italy',
            'nl' => 'Netherlands', 'se' => 'Sweden', 'no' => 'Norway', 'dk' => 'Denmark',
            'in' => 'India', 'pk' => 'Pakistan', 'bd' => 'Bangladesh', 'ae' => 'UAE',
            'sa' => 'Saudi Arabia', 'sg' => 'Singapore', 'my' => 'Malaysia', 'id' => 'Indonesia',
            'jp' => 'Japan', 'kr' => 'South Korea', 'cn' => 'China', 'hk' => 'Hong Kong',
            'br' => 'Brazil', 'mx' => 'Mexico', 'ar' => 'Argentina',
            'za' => 'South Africa', 'ng' => 'Nigeria', 'eg' => 'Egypt',
            'tr' => 'Turkey', 'ru' => 'Russia', 'pl' => 'Poland', 'nz' => 'New Zealand',
        ];
    }

    /** @return array<string, string> */
    private function languages(): array
    {
        return [
            'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German',
            'it' => 'Italian', 'pt' => 'Portuguese', 'nl' => 'Dutch', 'sv' => 'Swedish',
            'ar' => 'Arabic', 'ur' => 'Urdu', 'hi' => 'Hindi', 'bn' => 'Bengali',
            'ja' => 'Japanese', 'ko' => 'Korean', 'zh-cn' => 'Chinese (Simplified)', 'zh-tw' => 'Chinese (Traditional)',
            'ru' => 'Russian', 'tr' => 'Turkish', 'pl' => 'Polish', 'id' => 'Indonesian',
            'ms' => 'Malay', 'th' => 'Thai', 'vi' => 'Vietnamese',
        ];
    }
}
