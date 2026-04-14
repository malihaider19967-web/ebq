<?php

namespace App\Livewire\Keywords;

use App\Models\SearchConsoleData;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class KeywordsTable extends Component
{
    use WithPagination;

    public int $websiteId = 0;
    public string $search = '';
    public string $view = 'aggregated';
    public ?string $device = null;
    public ?string $from = null;
    public ?string $to = null;

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

    public function updatedView(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $rows = collect();

        if ($this->websiteId) {
            $base = SearchConsoleData::query()
                ->where('website_id', $this->websiteId)
                ->forDateRange($this->from, $this->to)
                ->when($this->search, fn ($q) => $q->where('query', 'like', "%{$this->search}%"))
                ->when($this->device, fn ($q) => $q->where('device', $this->device));

            if ($this->view === 'daily') {
                $rows = (clone $base)
                    ->select('date', 'query', 'clicks', 'impressions', 'ctr', 'position')
                    ->orderByDesc('date')
                    ->orderByDesc('clicks')
                    ->paginate(25);
            } else {
                $rows = (clone $base)
                    ->select(
                        'query',
                        DB::raw('SUM(clicks) as clicks'),
                        DB::raw('SUM(impressions) as impressions'),
                        DB::raw('AVG(ctr) as ctr'),
                        DB::raw('AVG(position) as position'),
                    )
                    ->groupBy('query')
                    ->orderByDesc('clicks')
                    ->paginate(25);
            }
        }

        return view('livewire.keywords.keywords-table', compact('rows'));
    }
}
