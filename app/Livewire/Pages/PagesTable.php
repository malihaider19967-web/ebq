<?php

namespace App\Livewire\Pages;

use App\Models\SearchConsoleData;
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

        $allowed = ['page', 'total_clicks', 'total_impressions', 'avg_ctr', 'avg_position', 'last_indexed_at'];
        $sortBy = in_array($this->sortBy, $allowed) ? $this->sortBy : 'total_clicks';

        if ($this->websiteId && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $rows = SearchConsoleData::query()
                ->select(
                    'page',
                    DB::raw('SUM(clicks) as total_clicks'),
                    DB::raw('SUM(impressions) as total_impressions'),
                    DB::raw('AVG(position) as avg_position'),
                    DB::raw('AVG(ctr) as avg_ctr'),
                    DB::raw('MAX(page_indexing_statuses.last_indexed_at) as last_indexed_at'),
                )
                ->leftJoin('page_indexing_statuses', function ($join): void {
                    $join->on('page_indexing_statuses.website_id', '=', 'search_console_data.website_id')
                        ->on('page_indexing_statuses.page', '=', 'search_console_data.page');
                })
                ->where('search_console_data.website_id', $this->websiteId)
                ->when($this->search, fn ($q) => $q->where('page', 'like', "%{$this->search}%"))
                ->groupBy('page')
                ->orderBy($sortBy, $this->sortDir)
                ->paginate(20);
        }

        return view('livewire.pages.pages-table', compact('rows'));
    }
}
