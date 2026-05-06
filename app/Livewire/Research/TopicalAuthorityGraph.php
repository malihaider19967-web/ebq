<?php

namespace App\Livewire\Research;

use App\Models\Research\Niche;
use App\Models\Research\NicheTopicCluster;
use App\Models\Research\WebsitePageKeyword;
use Livewire\Attributes\Url;
use Livewire\Component;

class TopicalAuthorityGraph extends Component
{
    public int $websiteId = 0;

    #[Url(as: 'niche')]
    public ?int $nicheId = null;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    public function render()
    {
        $niches = $this->websiteId > 0
            ? Niche::query()
                ->whereIn('id', function ($q) {
                    $q->select('niche_id')->from('website_niche_map')->where('website_id', $this->websiteId);
                })
                ->orderByDesc('id')
                ->get(['id', 'name', 'slug'])
            : collect();

        if ($niches->isEmpty()) {
            $niches = Niche::query()->whereNull('parent_id')->orderBy('name')->get(['id', 'name', 'slug']);
        }

        $rows = $this->nicheId === null
            ? collect()
            : NicheTopicCluster::query()
                ->where('niche_id', $this->nicheId)
                ->with('cluster.keywords:id,query')
                ->orderByDesc('priority_score')
                ->limit(40)
                ->get();

        $coveredKeywordIds = $this->websiteId === 0
            ? collect()
            : WebsitePageKeyword::query()
                ->whereIn('page_id', function ($q) {
                    $q->select('id')->from('website_pages')->where('website_id', $this->websiteId);
                })
                ->pluck('keyword_id')
                ->unique();

        return view('livewire.research.topical-authority-graph', compact('niches', 'rows', 'coveredKeywordIds'));
    }
}
