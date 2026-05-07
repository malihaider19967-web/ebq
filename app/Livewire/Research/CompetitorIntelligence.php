<?php

namespace App\Livewire\Research;

use App\Models\Research\CompetitorOutlink;
use App\Models\Research\CompetitorPage;
use App\Models\Research\CompetitorScan;
use App\Models\Research\CompetitorTopic;
use App\Models\Research\Keyword;
use App\Models\Research\SerpResult;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Two-tier competitor view.
 *
 *   Tier 1 (preferred): if a completed competitor_scans row exists for
 *   the typed domain, render the rich crawled-page data — top pages,
 *   topics, extracted keywords, external link summary.
 *
 *   Tier 2 (fallback): use the SERP-derived path that's been here since
 *   Phase 3 — keywords this domain shows up for in our crawled SERPs.
 *
 * The UI surface is the same input field; the data source switches
 * automatically based on whether the operator has scraped this
 * competitor yet.
 */
class CompetitorIntelligence extends Component
{
    #[Url(as: 'domain')]
    public string $domain = '';

    public function render(\App\Services\Research\BacklinksLookupService $backlinksLookup)
    {
        $payload = [
            'mode' => 'empty',
            'scan' => null,
            'pages' => collect(),
            'topics' => collect(),
            'topExternalDomains' => collect(),
            'serpKeywords' => collect(),
            'backlinksSummary' => null,
        ];

        if ($this->domain === '') {
            return view('livewire.research.competitor-intelligence', $payload);
        }

        $domain = mb_strtolower(preg_replace('/^www\./', '', $this->domain) ?? $this->domain);

        // Backlinks live independent of whether we've scraped THIS
        // domain — they come from outlinks recorded in OTHER scans, so
        // even a never-scraped domain may already have rich backlink
        // data once we've crawled enough sites that link to it. This is
        // the SEMrush/Ahrefs effect that compounds as the corpus grows.
        $payload['backlinksSummary'] = $backlinksLookup->summary($domain, linkLimit: 30, anchorLimit: 12);

        $scan = CompetitorScan::query()
            ->where('seed_domain', $domain)
            ->where('status', CompetitorScan::STATUS_DONE)
            ->orderByDesc('finished_at')
            ->first();

        if ($scan !== null) {
            $payload['mode'] = 'scan';
            $payload['scan'] = $scan;
            $payload['pages'] = CompetitorPage::query()
                ->where('competitor_scan_id', $scan->id)
                ->orderByDesc('word_count')
                ->limit(50)
                ->get(['id', 'url', 'title', 'meta_description', 'word_count', 'depth', 'is_external']);
            $payload['topics'] = CompetitorTopic::query()
                ->where('competitor_scan_id', $scan->id)
                ->orderByDesc('page_count')
                ->limit(15)
                ->get();
            $payload['topExternalDomains'] = CompetitorOutlink::query()
                ->where('competitor_scan_id', $scan->id)
                ->where('is_external', true)
                ->select('to_domain', DB::raw('COUNT(*) as link_count'))
                ->groupBy('to_domain')
                ->orderByDesc('link_count')
                ->limit(10)
                ->get();

            return view('livewire.research.competitor-intelligence', $payload);
        }

        // Fallback: SERP-derived view from Phase 3.
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

        if ($keywordIds !== []) {
            $payload['mode'] = 'serp';
            $payload['serpKeywords'] = Keyword::query()
                ->whereIn('keywords.id', $keywordIds)
                ->leftJoin('keyword_intelligence', 'keyword_intelligence.keyword_id', '=', 'keywords.id')
                ->orderByDesc('keyword_intelligence.search_volume')
                ->select(['keywords.*', 'keyword_intelligence.search_volume', 'keyword_intelligence.difficulty_score'])
                ->get();
        }

        return view('livewire.research.competitor-intelligence', $payload);
    }
}
