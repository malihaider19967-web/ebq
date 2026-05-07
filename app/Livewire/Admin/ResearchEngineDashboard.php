<?php

namespace App\Livewire\Admin;

use App\Jobs\Research\RunCompetitorScanJob;
use App\Models\Research\CompetitorOutlink;
use App\Models\Research\CompetitorPage;
use App\Models\Research\CompetitorScan;
use App\Models\Research\ResearchTarget;
use App\Support\ResearchEngineSettings;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Live admin dashboard for the continuous research engine. Polls every
 * 5s so the operator can watch scans pick up, progress, finish, and
 * outlinks fan out into the queue without refreshing.
 *
 * Action buttons (Run now / Pause / Resume / Delete) flip the underlying
 * research_target row; the next scheduler tick (or this same dashboard's
 * Run-now) will pick the resumed target up.
 */
class ResearchEngineDashboard extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'source')]
    public string $sourceFilter = '';

    public string $flash = '';

    public function runNow(int $targetId): void
    {
        if (ResearchEngineSettings::enginePaused()) {
            $this->flash = 'Engine is paused — unpause first.';
            return;
        }

        $target = ResearchTarget::query()->find($targetId);
        if ($target === null) {
            return;
        }

        if (CompetitorScan::query()->where('seed_domain', $target->domain)->active()->exists()) {
            $this->flash = "Scan already running for {$target->domain}.";
            return;
        }

        $scan = CompetitorScan::create([
            'website_id' => $target->attached_website_id,
            'triggered_by_user_id' => auth()->id(),
            'seed_domain' => $target->domain,
            'seed_url' => $target->root_url ?: 'https://'.$target->domain.'/',
            'seed_keywords' => $target->seed_keywords ?? [],
            'caps' => [
                'max_total_pages' => 250,
                'max_pages_per_external_domain' => 5,
                'max_depth' => 4,
            ],
            'status' => CompetitorScan::STATUS_QUEUED,
        ]);

        $target->forceFill(['status' => ResearchTarget::STATUS_SCANNING])->save();
        RunCompetitorScanJob::dispatch($scan->id);

        $this->flash = "Scan #{$scan->id} dispatched for {$target->domain}.";
    }

    public function pauseTarget(int $targetId): void
    {
        $target = ResearchTarget::query()->find($targetId);
        if ($target === null || $target->status === ResearchTarget::STATUS_SCANNING) {
            return;
        }
        $target->forceFill(['status' => ResearchTarget::STATUS_PAUSED])->save();
        $this->flash = "Paused {$target->domain}.";
    }

    public function resumeTarget(int $targetId): void
    {
        $target = ResearchTarget::query()->find($targetId);
        if ($target === null) {
            return;
        }
        $target->forceFill(['status' => ResearchTarget::STATUS_QUEUED])->save();
        $this->flash = "Resumed {$target->domain}.";
    }

    public function deleteTarget(int $targetId): void
    {
        $target = ResearchTarget::query()->find($targetId);
        if ($target === null || $target->status === ResearchTarget::STATUS_SCANNING) {
            return;
        }
        $domain = $target->domain;
        $target->delete();
        $this->flash = "Removed {$domain}.";
    }

    public function render()
    {
        $now = Carbon::now();
        $startOfDay = $now->copy()->startOfDay();

        $tiles = [
            'queued' => ResearchTarget::query()->where('status', ResearchTarget::STATUS_QUEUED)->count(),
            'scanning' => ResearchTarget::query()->where('status', ResearchTarget::STATUS_SCANNING)->count(),
            'paused' => ResearchTarget::query()->where('status', ResearchTarget::STATUS_PAUSED)->count(),
            'done_total' => ResearchTarget::query()->where('status', ResearchTarget::STATUS_DONE)->count(),
            'scans_today' => CompetitorScan::query()->where('started_at', '>=', $startOfDay)->count(),
            'pages_today' => CompetitorPage::query()->where('created_at', '>=', $startOfDay)->count(),
            'links_today' => CompetitorOutlink::query()->where('created_at', '>=', $startOfDay)->count(),
        ];

        $running = CompetitorScan::query()
            ->whereIn('status', [CompetitorScan::STATUS_RUNNING, CompetitorScan::STATUS_QUEUED, CompetitorScan::STATUS_CANCELLING])
            ->with('triggeredBy:id,name')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // Hand-rolled CASE for cross-DB sort (SQLite test fixtures lack
        // MySQL's FIELD()): scanning first, then queued, paused, done,
        // anything else last.
        $queue = ResearchTarget::query()
            ->with('attachedWebsite:id,domain')
            ->when($this->search !== '', fn ($q) => $q->where('domain', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->sourceFilter !== '', fn ($q) => $q->where('source', $this->sourceFilter))
            ->orderByRaw("CASE status WHEN 'scanning' THEN 1 WHEN 'queued' THEN 2 WHEN 'paused' THEN 3 WHEN 'done' THEN 4 ELSE 5 END")
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $recentScans = CompetitorScan::query()
            ->whereIn('status', [CompetitorScan::STATUS_DONE, CompetitorScan::STATUS_FAILED])
            ->orderByDesc('finished_at')
            ->limit(15)
            ->get();

        return view('livewire.admin.research-engine-dashboard', [
            'tiles' => $tiles,
            'running' => $running,
            'queue' => $queue,
            'recentScans' => $recentScans,
            'enginePaused' => ResearchEngineSettings::enginePaused(),
            'autoDiscoveryDisabled' => ResearchEngineSettings::autoDiscoveryDisabled(),
        ]);
    }
}
