<?php

namespace App\Livewire\Pages;

use App\Models\SearchConsoleData;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class PagesTable extends Component
{
    use WithPagination;

    public int $websiteId = 0;
    public string $search = '';

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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $rows = collect();

        if ($this->websiteId) {
            $rows = SearchConsoleData::query()
                ->select(
                    'page',
                    DB::raw('SUM(clicks) as total_clicks'),
                    DB::raw('SUM(impressions) as total_impressions'),
                    DB::raw('AVG(position) as avg_position'),
                    DB::raw('AVG(ctr) as avg_ctr'),
                )
                ->where('website_id', $this->websiteId)
                ->when($this->search, fn ($q) => $q->where('page', 'like', "%{$this->search}%"))
                ->groupBy('page')
                ->orderByDesc('total_clicks')
                ->paginate(20);
        }

        return view('livewire.pages.pages-table', compact('rows'));
    }
}
