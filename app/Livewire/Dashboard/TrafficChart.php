<?php

namespace App\Livewire\Dashboard;

use App\Models\AnalyticsData;
use App\Models\SearchConsoleData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        $latestClicksPair = null;
        $latestUsersPair = null;

        if ($this->websiteId && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $user = Auth::user();
            $tz = display_timezone($user);
            $today = Carbon::today($tz);
            $end = $today->copy()->subDay();
            $start = $end->copy()->subDays(29);
            $cacheKey = sprintf(
                'traffic_chart:v3:%d:%d:%s:%s:%s',
                $this->websiteId,
                (int) $user->id,
                str_replace('/', '_', $tz),
                $start->toDateString(),
                $end->toDateString()
            );

            $cached = Cache::remember($cacheKey, 600, function () use ($start, $end, $user) {
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

                return [
                    'days' => $allDates->map(fn ($d) => [
                        'date' => format_user_date(is_string($d) ? $d : (string) $d, 'M d', $user),
                        'clicks' => (int) ($clicks[$d] ?? 0),
                        'users' => (int) ($users[$d] ?? 0),
                    ]),
                    // Keep anomaly comparison on real metric samples only.
                    'clicks_pair' => $clicks->count() >= 2 ? $clicks->slice(-2, 2, true)->values()->all() : null,
                    'users_pair' => $users->count() >= 2 ? $users->slice(-2, 2, true)->values()->all() : null,
                ];
            });

            // Backward compatibility in case an older cache payload is present.
            if ($cached instanceof Collection) {
                $days = $cached;
            } else {
                $days = collect($cached['days'] ?? []);
                $latestClicksPair = $cached['clicks_pair'] ?? null;
                $latestUsersPair = $cached['users_pair'] ?? null;
            }

            if (is_array($latestUsersPair) && count($latestUsersPair) === 2) {
                [$prevUsers, $lastUsers] = array_map('intval', $latestUsersPair);
                if ($prevUsers > 0 && $lastUsers < ($prevUsers * 0.75)) {
                    $anomalies[] = 'Users dropped more than 25% day-over-day.';
                }
            }

            if (is_array($latestClicksPair) && count($latestClicksPair) === 2) {
                [$prevClicks, $lastClicks] = array_map('intval', $latestClicksPair);
                if ($prevClicks > 0 && $lastClicks < ($prevClicks * 0.75)) {
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
