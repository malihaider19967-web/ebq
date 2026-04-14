<?php

namespace App\Livewire\Pages;

use App\Models\SearchConsoleData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PageDetail extends Component
{
    use WithPagination;

    public int $websiteId = 0;
    public string $pageUrl = '';
    public string $sortBy = 'total_clicks';
    public string $sortDir = 'desc';

    public function mount(string $pageUrl): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
        $this->pageUrl = urldecode($pageUrl);
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

    public function render()
    {
        $summary = null;
        $keywords = collect();

        $allowed = ['query', 'total_clicks', 'total_impressions', 'avg_ctr', 'avg_position'];
        $sortBy = in_array($this->sortBy, $allowed) ? $this->sortBy : 'total_clicks';

        if ($this->websiteId && $this->pageUrl && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $summary = SearchConsoleData::query()
                ->select(
                    DB::raw('SUM(clicks) as total_clicks'),
                    DB::raw('SUM(impressions) as total_impressions'),
                    DB::raw('AVG(position) as avg_position'),
                    DB::raw('AVG(ctr) as avg_ctr'),
                )
                ->where('website_id', $this->websiteId)
                ->where('page', $this->pageUrl)
                ->first();

            $keywords = SearchConsoleData::query()
                ->select(
                    'query',
                    DB::raw('SUM(clicks) as total_clicks'),
                    DB::raw('SUM(impressions) as total_impressions'),
                    DB::raw('AVG(position) as avg_position'),
                    DB::raw('AVG(ctr) as avg_ctr'),
                )
                ->where('website_id', $this->websiteId)
                ->where('page', $this->pageUrl)
                ->groupBy('query')
                ->orderBy($sortBy, $this->sortDir)
                ->paginate(20);
        }

        return view('livewire.pages.page-detail', compact('summary', 'keywords'));
    }
}
