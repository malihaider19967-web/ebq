<?php

namespace App\Livewire\Dashboard;

use App\Models\AnalyticsData;
use App\Models\SearchConsoleData;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class KpiCards extends Component
{
    public int $websiteId;

    public function render()
    {
        $data = Cache::remember("kpis:{$this->websiteId}", 600, function () {
            return [
                'clicks' => (int) SearchConsoleData::where('website_id', $this->websiteId)->sum('clicks'),
                'impressions' => (int) SearchConsoleData::where('website_id', $this->websiteId)->sum('impressions'),
                'users' => (int) AnalyticsData::where('website_id', $this->websiteId)->sum('users'),
                'sessions' => (int) AnalyticsData::where('website_id', $this->websiteId)->sum('sessions'),
            ];
        });

        return view('livewire.dashboard.kpi-cards', compact('data'));
    }
}
