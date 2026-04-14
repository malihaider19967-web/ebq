<?php

namespace App\Livewire\Dashboard;

use App\Models\AnalyticsData;
use App\Models\SearchConsoleData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class TrafficChart extends Component
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
        $days = collect();

        if ($this->websiteId && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $days = Cache::remember("traffic_chart:{$this->websiteId}", 600, function () {
                $clicks = SearchConsoleData::where('website_id', $this->websiteId)
                    ->selectRaw('date, SUM(clicks) as clicks')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('clicks', 'date');

                $users = AnalyticsData::where('website_id', $this->websiteId)
                    ->selectRaw('date, SUM(users) as users')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('users', 'date');

                $allDates = $clicks->keys()->merge($users->keys())->unique()->sort()->values();

                return $allDates->map(fn ($d) => [
                    'date' => $d instanceof \DateTimeInterface ? $d->format('M d') : $d,
                    'clicks' => (int) ($clicks[$d] ?? 0),
                    'users' => (int) ($users[$d] ?? 0),
                ]);
            });
        }

        return view('livewire.dashboard.traffic-chart', ['days' => $days]);
    }
}
