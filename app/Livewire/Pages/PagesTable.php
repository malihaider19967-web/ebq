<?php

namespace App\Livewire\Pages;

use App\Models\PageAuditReport;
use App\Models\SearchConsoleData;
use App\Models\Website;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class PagesTable extends Component
{
    use WithPagination;

    public int $websiteId = 0;
    public string $search = '';
    public string $sortBy = 'total_clicks';
    public string $sortDir = 'desc';

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $rows = collect();
        $gscKeywordLookbackDays = null;

        $allowed = ['page', 'total_clicks', 'total_impressions', 'avg_ctr', 'avg_position', 'last_google_status_checked_at'];
        $sortBy = in_array($this->sortBy, $allowed) ? $this->sortBy : 'total_clicks';
        $sortColumn = $sortBy === 'page' ? 'search_console_data.page' : $sortBy;

        if ($this->websiteId && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $websiteRow = Website::query()->find($this->websiteId);
            $gscKeywordWindowFrom = $websiteRow?->gscKeywordWindowStartDate();
            if ($websiteRow) {
                $gscKeywordLookbackDays = $websiteRow->effectiveGscKeywordLookbackDays();
            }

            $rows = SearchConsoleData::query()
                ->select(
                    DB::raw('search_console_data.page as page'),
                    DB::raw('SUM(clicks) as total_clicks'),
                    DB::raw('SUM(impressions) as total_impressions'),
                    DB::raw('AVG(position) as avg_position'),
                    DB::raw('AVG(ctr) as avg_ctr'),
                    DB::raw('MAX(page_indexing_statuses.last_google_status_checked_at) as last_google_status_checked_at'),
                    DB::raw('MAX(page_indexing_statuses.google_verdict) as google_verdict'),
                    DB::raw('MAX(page_indexing_statuses.google_coverage_state) as google_coverage_state'),
                    DB::raw('MAX(page_indexing_statuses.google_last_crawl_at) as google_last_crawl_at'),
                )
                ->leftJoin('page_indexing_statuses', function ($join): void {
                    $join->on('page_indexing_statuses.website_id', '=', 'search_console_data.website_id')
                        ->on('page_indexing_statuses.page', '=', 'search_console_data.page');
                })
                ->where('search_console_data.website_id', $this->websiteId)
                ->when($gscKeywordWindowFrom, fn ($q) => $q->whereDate('search_console_data.date', '>=', $gscKeywordWindowFrom))
                ->when($this->search, fn ($q) => $q->where('search_console_data.page', 'like', "%{$this->search}%"))
                ->groupBy('search_console_data.page')
                ->orderBy($sortColumn, $this->sortDir)
                ->paginate(20);

            $pageLocaleByHash = [];
            if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->isNotEmpty()) {
                $hashes = $rows->getCollection()->map(fn ($row) => hash('sha256', $row->page))->unique()->values()->all();
                $reports = PageAuditReport::query()
                    ->where('website_id', $this->websiteId)
                    ->whereIn('page_hash', $hashes)
                    ->get(['page_hash', 'result']);
                foreach ($reports as $report) {
                    $pl = is_array($report->result) ? ($report->result['page_locale'] ?? null) : null;
                    if (is_array($pl)) {
                        $pageLocaleByHash[$report->page_hash] = $pl;
                    }
                }
            }
        } else {
            $pageLocaleByHash = [];
        }

        return view('livewire.pages.pages-table', compact('rows', 'pageLocaleByHash', 'gscKeywordLookbackDays'));
    }
}
