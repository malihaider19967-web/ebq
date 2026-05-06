<?php

namespace App\Livewire\Research;

use App\Models\Research\Keyword;
use App\Models\Research\SerpResult;
use Livewire\Attributes\Url;
use Livewire\Component;

class CompetitorIntelligence extends Component
{
    #[Url(as: 'domain')]
    public string $domain = '';

    public function render()
    {
        $keywords = collect();

        if ($this->domain !== '') {
            $domain = mb_strtolower(preg_replace('/^www\./', '', $this->domain) ?? $this->domain);

            $keywordIds = SerpResult::query()
                ->where('domain', $domain)
                ->where('rank', '<=', 10)
                ->whereIn('snapshot_id', function ($q) {
                    $q->select('id')->from('serp_snapshots');
                })
                ->with('snapshot:id,keyword_id')
                ->limit(500)
                ->get()
                ->pluck('snapshot.keyword_id')
                ->filter()
                ->unique()
                ->take(100)
                ->all();

            $keywords = Keyword::query()
                ->whereIn('keywords.id', $keywordIds)
                ->leftJoin('keyword_intelligence', 'keyword_intelligence.keyword_id', '=', 'keywords.id')
                ->orderByDesc('keyword_intelligence.search_volume')
                ->select(['keywords.*', 'keyword_intelligence.search_volume', 'keyword_intelligence.difficulty_score'])
                ->get();
        }

        return view('livewire.research.competitor-intelligence', compact('keywords'));
    }
}
