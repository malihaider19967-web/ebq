<?php

namespace App\Livewire\Dashboard;

use App\Services\ReportDataService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Dashboard card: top 5 quick-wins (low-competition keywords with volume
 * where this site doesn't rank top-10 yet). Hidden when the list is empty
 * so new sites don't see a placeholder frame.
 */
class QuickWinsCard extends Component
{
    public int $websiteId = 0;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
    }

    public function render()
    {
        $rows = [];

        if ($this->websiteId && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $rows = Cache::remember(
                'quick_wins_card:'.$this->websiteId,
                600,
                fn () => app(ReportDataService::class)->quickWins($this->websiteId, 5),
            );
        }

        return view('livewire.dashboard.quick-wins-card', [
            'rows' => $rows,
        ]);
    }
}
