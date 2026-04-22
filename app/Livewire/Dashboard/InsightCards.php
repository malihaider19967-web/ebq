<?php

namespace App\Livewire\Dashboard;

use App\Services\ReportDataService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class InsightCards extends Component
{
    public int $websiteId = 0;

    #[Url(as: 'country', history: true)]
    public string $country = '';

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        $this->country = '';
    }

    #[On('country-changed')]
    public function onCountryChanged(string $country): void
    {
        $this->country = $country;
    }

    public function render()
    {
        $counts = ['cannibalizations' => 0, 'striking_distance' => 0, 'indexing_fails_with_traffic' => 0, 'content_decay' => 0];

        if ($this->websiteId && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $country = $this->country !== '' ? $this->country : null;
            $cacheKey = 'insights:counts:'.$this->websiteId.':'.($country ?? 'all');
            $counts = Cache::remember(
                $cacheKey,
                600,
                fn () => app(ReportDataService::class)->insightCounts($this->websiteId, $country),
            );
        }

        return view('livewire.dashboard.insight-cards', [
            'counts' => $counts,
            'country' => $this->country,
        ]);
    }
}
