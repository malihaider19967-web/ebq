<?php

namespace App\Livewire\Research;

use App\Models\Research\Keyword;
use App\Models\Research\NicheAggregate;
use App\Models\Research\SerpResult;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReverseKeywordResearch extends Component
{
    #[Url(as: 'domain')]
    public string $domain = '';

    public function render()
    {
        $rows = collect();

        if ($this->domain !== '') {
            $domain = mb_strtolower(preg_replace('/^www\./', '', $this->domain) ?? $this->domain);

            $serpRows = SerpResult::query()
                ->where('domain', $domain)
                ->where('rank', '<=', 20)
                ->whereIn('snapshot_id', function ($q) {
                    $q->select('id')->from('serp_snapshots');
                })
                ->with('snapshot:id,keyword_id')
                ->limit(500)
                ->get();

            $aggregateCtr = NicheAggregate::query()
                ->whereNull('keyword_id')
                ->get()
                ->keyBy('niche_id');

            foreach ($serpRows as $r) {
                $keyword = Keyword::query()->find($r->snapshot?->keyword_id);
                if ($keyword === null) {
                    continue;
                }
                $rows->push([
                    'keyword' => $keyword->query,
                    'rank' => $r->rank,
                    'url' => $r->url,
                ]);
            }
        }

        return view('livewire.research.reverse-keyword-research', ['rows' => $rows->take(100)->values()]);
    }
}
