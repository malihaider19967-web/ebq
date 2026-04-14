<?php

namespace App\Livewire\Dashboard;

use App\Models\AnalyticsData;
use App\Models\SearchConsoleData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class KpiCards extends Component
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
        $data = ['clicks' => 0, 'impressions' => 0, 'users' => 0, 'sessions' => 0];

        if ($this->websiteId && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $data = Cache::remember("kpis:{$this->websiteId}", 600, function () {
                return [
                    'clicks' => (int) SearchConsoleData::where('website_id', $this->websiteId)->sum('clicks'),
                    'impressions' => (int) SearchConsoleData::where('website_id', $this->websiteId)->sum('impressions'),
                    'users' => (int) AnalyticsData::where('website_id', $this->websiteId)->sum('users'),
                    'sessions' => (int) AnalyticsData::where('website_id', $this->websiteId)->sum('sessions'),
                ];
            });
        }

        return view('livewire.dashboard.kpi-cards', compact('data'));
    }
}
