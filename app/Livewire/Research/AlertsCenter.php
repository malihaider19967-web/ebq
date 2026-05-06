<?php

namespace App\Livewire\Research;

use App\Models\Research\KeywordAlert;
use Livewire\Component;
use Livewire\WithPagination;

class AlertsCenter extends Component
{
    use WithPagination;

    public int $websiteId = 0;
    public string $type = '';
    public bool $showAcknowledged = false;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    public function acknowledge(int $alertId): void
    {
        KeywordAlert::query()->where('id', $alertId)->where('website_id', $this->websiteId)->update(['acknowledged_at' => now()]);
    }

    public function render()
    {
        $alerts = KeywordAlert::query()
            ->with('keyword:id,query')
            ->where('website_id', $this->websiteId)
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when(! $this->showAcknowledged, fn ($q) => $q->whereNull('acknowledged_at'))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.research.alerts-center', compact('alerts'));
    }
}
