<?php

namespace App\Livewire\Admin;

use App\Models\Research\CompetitorScan;
use Livewire\Component;

class CompetitorScanMonitor extends Component
{
    public int $scanId;

    public function mount(int $scanId): void
    {
        $this->scanId = $scanId;
    }

    public function render()
    {
        $scan = CompetitorScan::query()
            ->with('triggeredBy:id,name,email', 'website:id,domain')
            ->find($this->scanId);

        $stale = $scan ? $scan->isHeartbeatStale() : false;
        $progressUrl = $scan?->progress['current_url'] ?? null;
        $maxPages = $scan?->caps['max_total_pages'] ?? null;
        $progressFraction = ($maxPages && $scan && $scan->page_count > 0)
            ? min(1.0, $scan->page_count / max(1, $maxPages))
            : 0.0;

        return view('livewire.admin.competitor-scan-monitor', [
            'scan' => $scan,
            'stale' => $stale,
            'progressUrl' => $progressUrl,
            'progressFraction' => $progressFraction,
        ]);
    }
}
