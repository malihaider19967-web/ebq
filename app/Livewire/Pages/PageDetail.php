<?php

namespace App\Livewire\Pages;

use App\Models\SearchConsoleData;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PageDetail extends Component
{
    use WithPagination;

    public int $websiteId = 0;
    public string $pageUrl = '';

    public function mount(string $pageUrl): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
        $this->pageUrl = urldecode($pageUrl);
    }

    public function render()
    {
        $summary = null;
        $keywords = collect();

        if ($this->websiteId && $this->pageUrl) {
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
                ->orderByDesc('total_clicks')
                ->paginate(20);
        }

        return view('livewire.pages.page-detail', compact('summary', 'keywords'));
    }
}
