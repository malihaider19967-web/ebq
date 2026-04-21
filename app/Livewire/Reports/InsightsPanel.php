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

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
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
        $counts = $hasAccess
            ? $service->insightCounts($this->websiteId)
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
                'cannibalization' => $service->cannibalizationReport($this->websiteId),
                'striking_distance' => $service->strikingDistance($this->websiteId),
                'content_decay' => $service->contentDecay($this->websiteId),
                'indexing_fails' => $service->indexingFailsWithTraffic($this->websiteId),
                'audit_performance' => app(AuditPerformanceService::class)->underperformingPages($this->websiteId),
                'backlink_impact' => app(BacklinkImpactService::class)->impactByTargetPage($this->websiteId),
            };
        }

        return view('livewire.reports.insights-panel', [
            'counts' => $counts,
            'data' => $data,
            'hasAccess' => $hasAccess,
        ]);
    }
}
