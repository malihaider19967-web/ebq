<?php

namespace App\Livewire\Research;

use App\Models\Research\CompetitorTopic;
use App\Models\Research\Keyword;
use App\Models\Research\Niche;
use App\Models\Research\NicheKeywordMap;
use App\Models\Research\WebsitePageKeyword;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Per-niche topic tree with the user's website's coverage colouring.
 *
 * Data model (post-rewrite): topics come from competitor_topics across
 * every scan, filtered by the niche's keyword set (via niche_keyword_map).
 * Coverage of each topic = how many of the topic's top keywords are
 * tracked on the user's WebsitePages via website_page_keyword_map.
 *
 * Replaces the niche_topic_clusters-driven version which was never
 * populated for scraper-derived keywords.
 */
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

        $rows = collect();
        $keywordCount = 0;

        if ($this->nicheId !== null) {
            $keywordIdsInNiche = NicheKeywordMap::query()
                ->where('niche_id', $this->nicheId)
                ->pluck('keyword_id')
                ->map(fn ($v) => (int) $v);
            $keywordCount = $keywordIdsInNiche->count();

            $coveredKeywordIds = $this->websiteId === 0
                ? collect()
                : WebsitePageKeyword::query()
                    ->whereIn('page_id', function ($q) {
                        $q->select('id')->from('website_pages')->where('website_id', $this->websiteId);
                    })
                    ->pluck('keyword_id')
                    ->unique();

            if ($keywordIdsInNiche->isNotEmpty()) {
                // Match on EITHER centroid OR any top_keyword_id —
                // see TopicExplorer for the rationale.
                $nicheKeywordSet = $keywordIdsInNiche->flip();
                $topics = CompetitorTopic::query()
                    ->where(function ($q) use ($keywordIdsInNiche) {
                        $q->whereIn('centroid_keyword_id', $keywordIdsInNiche);
                        foreach ($keywordIdsInNiche as $kid) {
                            $q->orWhere('top_keyword_ids', 'like', '%'.$kid.'%');
                        }
                    })
                    ->orderByDesc('page_count')
                    ->limit(400)
                    ->get()
                    ->filter(function ($topic) use ($nicheKeywordSet) {
                        if ($topic->centroid_keyword_id !== null && $nicheKeywordSet->has((int) $topic->centroid_keyword_id)) {
                            return true;
                        }
                        foreach ((array) $topic->top_keyword_ids as $kid) {
                            if (! is_int($kid) && ! ctype_digit((string) $kid)) {
                                continue;
                            }
                            if ($nicheKeywordSet->has((int) $kid)) {
                                return true;
                            }
                        }
                        return false;
                    })
                    ->take(40)
                    ->values();

                $rows = $topics->map(function (CompetitorTopic $topic) use ($coveredKeywordIds) {
                    $topicKeywordIds = collect((array) $topic->top_keyword_ids)
                        ->filter(fn ($v) => is_int($v) || ctype_digit((string) $v))
                        ->map(fn ($v) => (int) $v);
                    $covered = $topicKeywordIds->intersect($coveredKeywordIds)->count();
                    $total = $topicKeywordIds->count();
                    return (object) [
                        'name' => $topic->name,
                        'page_count' => (int) $topic->page_count,
                        'covered' => $covered,
                        'total' => $total,
                        'coverage' => $total > 0 ? $covered / $total : 0.0,
                    ];
                });
            }
        }

        return view('livewire.research.topical-authority-graph', [
            'niches' => $niches,
            'rows' => $rows,
            'keywordCount' => $keywordCount,
        ]);
    }
}
