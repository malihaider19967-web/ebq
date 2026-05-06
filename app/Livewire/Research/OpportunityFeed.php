<?php

namespace App\Livewire\Research;

use App\Models\Research\KeywordIntelligence;
use App\Services\Research\Intelligence\OpportunityEngine;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class OpportunityFeed extends Component
{
    public int $websiteId = 0;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    public function render(OpportunityEngine $engine)
    {
        $rows = $this->websiteId === 0
            ? collect()
            : DB::table('search_console_data')
                ->select('keyword_id', 'page')
                ->selectRaw('SUM(impressions) as imps, SUM(clicks) as clicks, AVG(position) as avg_pos')
                ->where('website_id', $this->websiteId)
                ->whereNotNull('keyword_id')
                ->whereDate('date', '>=', now()->subDays(30)->toDateString())
                ->groupBy('keyword_id', 'page')
                ->orderByDesc('imps')
                ->limit(100)
                ->get();

        $intelById = KeywordIntelligence::query()
            ->whereIn('keyword_id', $rows->pluck('keyword_id')->filter())
            ->get()
            ->keyBy('keyword_id');

        $keywordsById = \App\Models\Research\Keyword::query()
            ->whereIn('id', $rows->pluck('keyword_id')->filter())
            ->get(['id', 'query'])
            ->keyBy('id');

        $opportunities = [];
        foreach ($rows as $r) {
            $intel = $intelById->get($r->keyword_id);
            $score = $engine->score(
                impressions30d: (int) $r->imps,
                currentCtr: $r->imps > 0 ? (float) $r->clicks / (float) $r->imps : 0.0,
                currentPosition: (float) $r->avg_pos,
                searchVolume: $intel?->search_volume,
                difficulty: $intel?->difficulty_score,
                nicheCtrByPosition: [1 => 0.30, 2 => 0.18, 3 => 0.11, 4 => 0.08, 5 => 0.06, 6 => 0.04, 7 => 0.03, 8 => 0.025, 9 => 0.02, 10 => 0.018],
                targetPosition: 3,
            );
            $opportunities[] = [
                'keyword' => $keywordsById->get($r->keyword_id)?->query ?? '—',
                'page' => $r->page,
                'imps' => (int) $r->imps,
                'pos' => round((float) $r->avg_pos, 1),
                'score' => $score['score'],
            ];
        }

        usort($opportunities, fn ($a, $b) => $b['score'] <=> $a['score']);

        return view('livewire.research.opportunity-feed', ['opportunities' => collect($opportunities)]);
    }
}
