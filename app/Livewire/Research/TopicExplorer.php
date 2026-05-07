<?php

namespace App\Livewire\Research;

use App\Models\Research\CompetitorScanKeyword;
use App\Models\Research\CompetitorTopic;
use App\Models\Research\Keyword;
use App\Models\Research\Niche;
use App\Models\Research\NicheKeywordMap;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Topics per niche, surfaced from `competitor_topics` (the rich source
 * the scraper actually populates) joined to niches via
 * `niche_keyword_map`. Aggregates across every scan whose topics
 * involve niche-relevant keywords.
 */
class TopicExplorer extends Component
{
    #[Url(as: 'niche')]
    public ?int $nicheId = null;

    public function render()
    {
        $niches = Niche::query()
            ->where('is_approved', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'parent_id']);

        $payload = [
            'niches' => $niches,
            'topics' => collect(),
            'rankings' => collect(),
            'keywordCount' => 0,
            'topicKeywordPhrases' => collect(),
        ];

        if ($this->nicheId === null) {
            return view('livewire.research.topic-explorer', $payload);
        }

        $keywordIds = NicheKeywordMap::query()
            ->where('niche_id', $this->nicheId)
            ->pluck('keyword_id')
            ->map(fn ($v) => (int) $v);

        $payload['keywordCount'] = $keywordIds->count();

        if ($keywordIds->isEmpty()) {
            return view('livewire.research.topic-explorer', $payload);
        }

        // Match on EITHER centroid_keyword_id OR any id in
        // top_keyword_ids JSON. The centroid is YAKE's top-1 phrase
        // per cluster and often isn't what the niche-mapper hooked
        // onto. Broadening surfaces topics whose top phrases include
        // niche keywords even when the centroid wasn't classifiable.
        $keywordIdSet = $keywordIds->flip();
        $topics = CompetitorTopic::query()
            ->with(['scan:id,seed_domain,finished_at', 'centroid:id,query'])
            ->where(function ($q) use ($keywordIds) {
                $q->whereIn('centroid_keyword_id', $keywordIds);
                foreach ($keywordIds as $kid) {
                    // LIKE-based prefilter against the JSON column is
                    // intentionally permissive — the in-PHP filter
                    // below tightens it to actual array membership.
                    $q->orWhere('top_keyword_ids', 'like', '%'.$kid.'%');
                }
            })
            ->orderByDesc('page_count')
            ->limit(500)
            ->get()
            ->filter(function ($topic) use ($keywordIdSet) {
                if ($topic->centroid_keyword_id !== null && $keywordIdSet->has((int) $topic->centroid_keyword_id)) {
                    return true;
                }
                foreach ((array) $topic->top_keyword_ids as $kid) {
                    if (! is_int($kid) && ! ctype_digit((string) $kid)) {
                        continue;
                    }
                    if ($keywordIdSet->has((int) $kid)) {
                        return true;
                    }
                }
                return false;
            })
            ->take(50)
            ->values();

        $allKeywordIds = $topics->flatMap(fn ($t) => (array) $t->top_keyword_ids)
            ->filter(fn ($v) => is_int($v) || ctype_digit((string) $v))
            ->map(fn ($v) => (int) $v)
            ->unique();

        $payload['topicKeywordPhrases'] = Keyword::query()
            ->whereIn('id', $allKeywordIds)
            ->pluck('query', 'id');

        $payload['topics'] = $topics;

        $payload['rankings'] = CompetitorScanKeyword::query()
            ->with(['keyword:id,query', 'scan:id,seed_domain,finished_at'])
            ->whereIn('keyword_id', $keywordIds)
            ->orderByDesc('total_occurrences')
            ->limit(30)
            ->get();

        return view('livewire.research.topic-explorer', $payload);
    }
}
