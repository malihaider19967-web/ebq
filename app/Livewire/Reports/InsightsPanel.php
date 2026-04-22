<?php

namespace App\Livewire\Reports;

use App\Services\AuditPerformanceService;
use App\Services\BacklinkImpactService;
use App\Services\ReportDataService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class InsightsPanel extends Component
{
    public int $websiteId = 0;

    #[Url(as: 'insight', history: true)]
    public string $tab = 'cannibalization';

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

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['cannibalization', 'striking_distance', 'content_decay', 'indexing_fails', 'audit_performance', 'backlink_impact'], true)) {
            return;
        }
        $this->tab = $tab;
    }

    public function render()
    {
        $user = Auth::user();
        $hasAccess = $this->websiteId > 0 && $user?->canViewWebsiteId($this->websiteId);

        $service = app(ReportDataService::class);
        $country = $this->country !== '' ? $this->country : null;

        $counts = $hasAccess
            ? $service->insightCounts($this->websiteId, $country)
            : ['cannibalizations' => 0, 'striking_distance' => 0, 'indexing_fails_with_traffic' => 0, 'content_decay' => 0];

        $data = [
            'cannibalization' => [],
            'striking_distance' => [],
            'content_decay' => ['pages' => [], 'has_yoy_history' => false],
            'indexing_fails' => [],
            'audit_performance' => [],
            'backlink_impact' => [],
        ];

        if ($hasAccess) {
            $data[$this->tab] = match ($this->tab) {
                'cannibalization' => $service->cannibalizationReport($this->websiteId, null, null, 50, $country),
                'striking_distance' => $service->strikingDistance($this->websiteId, null, null, 50, $country),
                'content_decay' => $service->contentDecay($this->websiteId, 25, $country),
                'indexing_fails' => $service->indexingFailsWithTraffic($this->websiteId, 14, 50, $country),
                // Audit + backlink reports aren't country-segmented today — they stay aggregate.
                'audit_performance' => app(AuditPerformanceService::class)->underperformingPages($this->websiteId),
                'backlink_impact' => app(BacklinkImpactService::class)->impactByTargetPage($this->websiteId),
            };
        }

        return view('livewire.reports.insights-panel', [
            'counts' => $counts,
            'data' => $data,
            'hasAccess' => $hasAccess,
            'country' => $this->country,
        ]);
    }
}
