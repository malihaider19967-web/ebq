<?php

namespace App\Livewire\Research;

use App\Models\Research\Keyword;
use App\Models\Research\NicheAggregate;
use App\Models\SearchConsoleData;
use App\Services\Research\Privacy\PrivacyGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * 90-day CTR / position trend per (page, keyword) for the current
 * website, benchmarked against niche_aggregates.avg_ctr_by_position
 * for the keyword's average rounded position. Pages whose CTR is below
 * the niche benchmark get flagged so the user can prioritise them.
 *
 * Uses NicheAggregate::aboveSamplingFloor() so n<3 niches don't leak.
 */
class PerformanceTracking extends Component
{
    public int $websiteId = 0;
    public int $rowsLimit = 25;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    public function render()
    {
        if ($this->websiteId === 0) {
            return view('livewire.research.performance-tracking', ['rows' => collect()]);
        }

        $rows = SearchConsoleData::query()
            ->select('keyword_id', 'page')
            ->selectRaw('SUM(impressions) as imps, SUM(clicks) as clicks, AVG(position) as avg_pos')
            ->where('website_id', $this->websiteId)
            ->whereNotNull('keyword_id')
            ->whereDate('date', '>=', Carbon::now()->subDays(90)->toDateString())
            ->groupBy('keyword_id', 'page')
            ->orderByDesc('imps')
            ->limit($this->rowsLimit)
            ->get();

        $keywordsById = Keyword::query()
            ->whereIn('id', $rows->pluck('keyword_id')->filter())
            ->get(['id', 'query'])
            ->keyBy('id');

        $nicheByKeyword = DB::table('niche_keyword_map')
            ->whereIn('keyword_id', $rows->pluck('keyword_id')->filter())
            ->orderByDesc('relevance_score')
            ->get()
            ->groupBy('keyword_id')
            ->map(fn ($g) => $g->first()->niche_id);

        $aggregatesByNiche = (new PrivacyGuard())->aggregateQuery()
            ->whereIn('niche_id', $nicheByKeyword->values()->all())
            ->whereNull('keyword_id')
            ->get()
            ->keyBy('niche_id');

        $report = $rows->map(function ($r) use ($keywordsById, $nicheByKeyword, $aggregatesByNiche) {
            $kw = $keywordsById->get($r->keyword_id);
            $imps = (int) $r->imps;
            $currentCtr = $imps > 0 ? (float) $r->clicks / (float) $imps : 0.0;
            $position = (int) round((float) $r->avg_pos);

            $nicheId = $nicheByKeyword->get($r->keyword_id);
            $benchmark = null;
            if ($nicheId !== null) {
                $aggregate = $aggregatesByNiche->get($nicheId);
                if ($aggregate instanceof NicheAggregate) {
                    $ctrByPos = is_array($aggregate->avg_ctr_by_position) ? $aggregate->avg_ctr_by_position : [];
                    $benchmark = $ctrByPos[(string) $position] ?? $ctrByPos[$position] ?? null;
                }
            }

            return [
                'keyword' => $kw?->query ?? '—',
                'page' => (string) $r->page,
                'avg_position' => round((float) $r->avg_pos, 1),
                'impressions' => $imps,
                'clicks' => (int) $r->clicks,
                'ctr' => round($currentCtr, 4),
                'benchmark' => $benchmark !== null ? round((float) $benchmark, 4) : null,
                'underperforming' => $benchmark !== null && $currentCtr < (float) $benchmark * 0.85,
            ];
        });

        return view('livewire.research.performance-tracking', ['rows' => $report]);
    }
}
