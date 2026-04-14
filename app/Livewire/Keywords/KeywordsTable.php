<?php

namespace App\Livewire\Keywords;

use App\Models\SearchConsoleData;
use Livewire\Component;
use Livewire\WithPagination;

class KeywordsTable extends Component
{
    use WithPagination;

    public int $websiteId;
    public string $search = '';
    public ?string $country = null;
    public ?string $device = null;
    public ?string $from = null;
    public ?string $to = null;

    public function render()
    {
        $rows = SearchConsoleData::query()
            ->where('website_id', $this->websiteId)
            ->forDateRange($this->from, $this->to)
            ->when($this->search, fn ($q) => $q->where('query', 'like', "%{$this->search}%"))
            ->when($this->country, fn ($q) => $q->where('country', $this->country))
            ->when($this->device, fn ($q) => $q->where('device', $this->device))
            ->orderByDesc('clicks')
            ->paginate(15);

        return view('livewire.keywords.keywords-table', compact('rows'));
    }
}
