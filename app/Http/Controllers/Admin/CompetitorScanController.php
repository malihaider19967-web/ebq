<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Research\RunCompetitorScanJob;
use App\Models\Research\CompetitorScan;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin surface for the Python competitor scraper. Runs are triggered
 * here, status is monitored on the show page (Livewire `wire:poll.2s`),
 * cancellations flip a status flag the Python worker reads.
 *
 * Active-scan-per-domain dedup is enforced in `store()` rather than via
 * a partial unique index so MySQL + SQLite test fixtures behave the same.
 */
class CompetitorScanController extends Controller
{
    public function index(): View
    {
        $scans = CompetitorScan::query()
            ->with('triggeredBy:id,name,email', 'website:id,domain')
            ->orderByDesc('created_at')
            ->paginate(40);

        return view('admin.research.competitor-scans.index', [
            'scans' => $scans,
        ]);
    }

    public function create(): View
    {
        return view('admin.research.competitor-scans.create', [
            'websites' => Website::query()->orderBy('domain')->get(['id', 'domain']),
            'defaults' => [
                'max_total_pages' => 250,
                'max_depth' => 4,
            ],
            'ceilings' => $this->ceilings(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cfg = $this->ceilings();

        $data = $request->validate([
            'seed_url' => 'required|url|max:2048',
            'website_id' => 'nullable|integer|exists:websites,id',
            'seed_keywords' => 'nullable|string|max:8000',
            'max_total_pages' => 'required|integer|min:10|max:'.$cfg['max_total_pages'],
            'max_depth' => 'required|integer|min:1|max:'.$cfg['max_depth'],
        ]);

        $seedDomain = $this->extractDomain((string) $data['seed_url']);
        if ($seedDomain === '') {
            return back()->withInput()->withErrors(['seed_url' => 'Could not parse a domain from the seed URL.']);
        }

        $hasActive = CompetitorScan::query()
            ->where('seed_domain', $seedDomain)
            ->active()
            ->exists();

        if ($hasActive) {
            return back()->withInput()->withErrors([
                'seed_url' => "A scan for {$seedDomain} is already in progress. Cancel it before starting another.",
            ]);
        }

        $seeds = collect(preg_split('/\r?\n/', (string) ($data['seed_keywords'] ?? '')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $scan = CompetitorScan::create([
            'website_id' => $data['website_id'] ?? null,
            'triggered_by_user_id' => Auth::id(),
            'seed_domain' => $seedDomain,
            'seed_url' => $data['seed_url'],
            'seed_keywords' => $seeds,
            'caps' => [
                'max_total_pages' => (int) $data['max_total_pages'],
                'max_depth' => (int) $data['max_depth'],
            ],
            'status' => CompetitorScan::STATUS_QUEUED,
        ]);

        RunCompetitorScanJob::dispatch($scan->id);

        return redirect()
            ->route('admin.research.competitor-scans.show', $scan)
            ->with('status', 'Scan queued.');
    }

    public function show(CompetitorScan $competitorScan): View
    {
        return view('admin.research.competitor-scans.show', [
            'scan' => $competitorScan->load('triggeredBy:id,name,email', 'website:id,domain'),
        ]);
    }

    public function cancel(CompetitorScan $competitorScan): RedirectResponse
    {
        if (! $competitorScan->isActive()) {
            return back()->withErrors(['status' => 'Scan is not active.']);
        }

        $competitorScan->forceFill([
            'status' => CompetitorScan::STATUS_CANCELLING,
            'cancelled_at' => Carbon::now(),
        ])->save();

        return back()->with('status', 'Cancellation requested. The worker will exit at the next heartbeat.');
    }

    public function markFailed(CompetitorScan $competitorScan): RedirectResponse
    {
        if ($competitorScan->status !== CompetitorScan::STATUS_RUNNING) {
            return back()->withErrors(['status' => 'Only running scans can be force-failed.']);
        }
        if (! $competitorScan->isHeartbeatStale(30)) {
            return back()->withErrors(['status' => 'Heartbeat is fresh — wait before marking failed.']);
        }

        $competitorScan->forceFill([
            'status' => CompetitorScan::STATUS_FAILED,
            'finished_at' => Carbon::now(),
            'error' => 'Marked failed manually after stale heartbeat.',
        ])->save();

        return back()->with('status', 'Scan marked failed.');
    }

    private function ceilings(): array
    {
        $scraper = \App\Support\ResearchEngineSettings::scraper();
        return [
            'max_total_pages' => (int) $scraper['ceiling_total_pages'],
            'max_depth' => (int) $scraper['ceiling_depth'],
        ];
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url(trim($url), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }
        $host = mb_strtolower($host);

        return preg_replace('/^www\./', '', $host) ?: $host;
    }
}
