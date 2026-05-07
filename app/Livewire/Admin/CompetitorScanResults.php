<?php

namespace App\Livewire\Admin;

use App\Models\Research\CompetitorOutlink;
use App\Models\Research\CompetitorPage;
use App\Models\Research\CompetitorScan;
use App\Models\Research\CompetitorScanKeyword;
use App\Models\Research\CompetitorTopic;
use App\Models\Research\Keyword;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Read-only results viewer for a completed (or in-flight) competitor
 * scan. Mounted from the admin show page; only renders meaningful data
 * once the scan has finished. While the scan is still running the
 * monitor component is the right surface — this one stays empty.
 */
class CompetitorScanResults extends Component
{
    public int $scanId;

    public function mount(int $scanId): void
    {
        $this->scanId = $scanId;
    }

    public function render(\App\Services\Research\BacklinksLookupService $backlinks)
    {
        $scan = CompetitorScan::query()->find($this->scanId);
        if ($scan === null) {
            return view('livewire.admin.competitor-scan-results', [
                'scan' => null,
                'topics' => collect(),
                'topKeywords' => collect(),
                'seedRankings' => collect(),
                'topExternalDomains' => collect(),
                'pages' => collect(),
                'backlinks' => null,
            ]);
        }

        // Topics — already aggregated at flush time.
        $topics = CompetitorTopic::query()
            ->where('competitor_scan_id', $scan->id)
            ->orderByDesc('page_count')
            ->limit(20)
            ->get();

        // Hydrate centroid + top keyword phrases from the keywords table
        // so the topic table shows phrases, not numeric IDs.
        $keywordIdsInTopics = collect();
        foreach ($topics as $topic) {
            $keywordIdsInTopics->push($topic->centroid_keyword_id);
            foreach ((array) $topic->top_keyword_ids as $kid) {
                $keywordIdsInTopics->push($kid);
            }
        }
        $keywordPhrases = Keyword::query()
            ->whereIn('id', $keywordIdsInTopics->filter()->unique())
            ->pluck('query', 'id');

        // Top extracted keywords across the whole scan: ranked by how
        // often they appear in topic top-keyword lists weighted by
        // topic page_count.
        $keywordWeights = [];
        foreach ($topics as $topic) {
            foreach ((array) $topic->top_keyword_ids as $kid) {
                if (! is_int($kid) && ! ctype_digit((string) $kid)) {
                    continue;
                }
                $kid = (int) $kid;
                $keywordWeights[$kid] = ($keywordWeights[$kid] ?? 0) + (int) $topic->page_count;
            }
        }
        arsort($keywordWeights);
        $topKeywords = collect(array_slice($keywordWeights, 0, 25, true))
            ->map(fn ($weight, $kid) => [
                'phrase' => $keywordPhrases[$kid] ?? null,
                'weight' => $weight,
            ])
            ->filter(fn ($r) => $r['phrase'] !== null)
            ->values();

        // Per-seed-keyword competitor rankings: which pages on this
        // domain target each seed keyword best.
        $seedRankings = CompetitorScanKeyword::query()
            ->with('keyword:id,query')
            ->where('competitor_scan_id', $scan->id)
            ->orderByDesc('total_occurrences')
            ->limit(50)
            ->get()
            ->map(function (CompetitorScanKeyword $row) use ($scan) {
                $topPages = collect((array) $row->top_pages_json)
                    ->take(5)
                    ->all();
                $pageIds = array_filter(array_map(fn ($r) => (int) ($r['page_id'] ?? 0), $topPages));
                $pageById = $pageIds === []
                    ? collect()
                    : CompetitorPage::query()
                        ->whereIn('id', $pageIds)
                        ->where('competitor_scan_id', $scan->id)
                        ->get(['id', 'url', 'title'])
                        ->keyBy('id');
                return [
                    'phrase' => $row->keyword?->query ?? '—',
                    'total_occurrences' => $row->total_occurrences,
                    'top_pages' => collect($topPages)->map(fn ($p) => [
                        'page' => $pageById->get((int) ($p['page_id'] ?? 0)),
                        'occurrences' => (int) ($p['occurrences'] ?? 0),
                        'density' => (float) ($p['density'] ?? 0),
                    ])->all(),
                ];
            });

        // Top external domains they link out to.
        $topExternalDomains = CompetitorOutlink::query()
            ->where('competitor_scan_id', $scan->id)
            ->where('is_external', true)
            ->select('to_domain', DB::raw('COUNT(*) as link_count'))
            ->groupBy('to_domain')
            ->orderByDesc('link_count')
            ->limit(15)
            ->get();

        // Sample of crawled pages (highest word_count first — usually the
        // meatiest content).
        $pages = CompetitorPage::query()
            ->where('competitor_scan_id', $scan->id)
            ->orderByDesc('word_count')
            ->limit(50)
            ->get(['id', 'url', 'title', 'meta_description', 'word_count', 'depth', 'is_external']);

        // Backlinks: cross-scan inverted view. Anything any other scan
        // saw linking to THIS scan's seed_domain. Grows in value as the
        // research engine accumulates more crawls — that's the
        // SEMrush/Ahrefs moat.
        $backlinksSummary = $backlinks->summary($scan->seed_domain, linkLimit: 50, anchorLimit: 15);

        return view('livewire.admin.competitor-scan-results', compact(
            'scan',
            'topics',
            'keywordPhrases',
            'topKeywords',
            'seedRankings',
            'topExternalDomains',
            'pages',
            'backlinksSummary',
        ));
    }
}
