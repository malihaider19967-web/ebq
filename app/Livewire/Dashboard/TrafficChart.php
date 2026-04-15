<?php

namespace App\Livewire\Dashboard;

use App\Models\AnalyticsData;
use App\Models\SearchConsoleData;
use Illuminate\Support\Carbon;
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
        $anomalies = [];

        if ($this->websiteId && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $today = Carbon::today(config('app.timezone'));
            $end = $today->copy()->subDay();
            $start = $end->copy()->subDays(29);
            $cacheKey = sprintf(
                'traffic_chart:%d:%s:%s',
                $this->websiteId,
                $start->toDateString(),
                $end->toDateString()
            );

            $days = Cache::remember($cacheKey, 600, function () use ($start, $end) {
                $clicks = SearchConsoleData::where('website_id', $this->websiteId)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->selectRaw('date, SUM(clicks) as clicks')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('clicks', 'date');

                $users = AnalyticsData::where('website_id', $this->websiteId)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
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

            $series = $days->values();
            $last = $series->last();
            $prev = $series->count() > 1 ? $series[$series->count() - 2] : null;
            if ($last && $prev) {
                if ($prev['users'] > 0 && $last['users'] < ($prev['users'] * 0.75)) {
                    $anomalies[] = 'Users dropped more than 25% day-over-day.';
                }
                if ($prev['clicks'] > 0 && $last['clicks'] < ($prev['clicks'] * 0.75)) {
                    $anomalies[] = 'Clicks dropped more than 25% day-over-day.';
                }
            }
        }

        return view('livewire.dashboard.traffic-chart', [
            'days' => $days,
            'anomalies' => $anomalies,
        ]);
    }
}
