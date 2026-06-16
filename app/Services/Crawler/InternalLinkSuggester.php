<?php

namespace App\Services\Crawler;

use App\Models\WebsiteInternalLink;
use App\Models\WebsitePage;
use App\Support\Crawler\TermExtractor;
use Illuminate\Support\Facades\DB;

/**
 * Proposes internal links TO under-linked pages (orphans + deep pages) FROM
 * topically-related, higher-authority pages — populating website_internal_links
 * with status='suggested'.
 *
 * Topical relevance is measured by overlap of each page's language-agnostic
 * significant terms (TF-IDF over content_terms, site-common words removed) —
 * NOT raw body_text. Idempotent: prior suggestions are replaced each run.
 */
class InternalLinkSuggester
{
    private const MAX_TARGETS = 100;
    private const CANDIDATE_POOL = 200;
    private const SUGGESTIONS_PER_TARGET = 3;

    public function __construct(private TermExtractor $extractor) {}

    /**
     * @param  array<string,int>|null  $df     site document-frequency map (built once
     *                                          in AnalyzeSiteJob); rebuilt here if null.
     * @param  int|null  $docs  sample doc count paired with $df.
     */
    public function suggest(int $crawlSiteId, ?array $df = null, ?int $docs = null): int
    {
        WebsiteInternalLink::where('crawl_site_id', $crawlSiteId)
            ->where('status', WebsiteInternalLink::STATUS_SUGGESTED)->delete();

        if ($df === null || $docs === null) {
            [$df, $docs] = $this->extractor->buildDf($crawlSiteId, (int) config('crawler.terms_df_sample', 3000));
        }

        $deepThreshold = (int) config('crawler.deep_page_threshold', 3);

        $targets = WebsitePage::where('crawl_site_id', $crawlSiteId)
            ->indexable()->whereNotNull('last_crawled_at')->where('http_status', 200)
            ->where(function ($q) use ($deepThreshold): void {
                $q->where('inbound_link_count', 0)->orWhere('click_depth', '>=', $deepThreshold);
            })
            ->orderByDesc('word_count')
            ->limit(self::MAX_TARGETS)
            ->get(['id', 'url', 'title', 'url_hash', 'content_terms']);

        if ($targets->isEmpty()) {
            return 0;
        }

        // Authority pool: well-linked indexable pages that can host new links.
        $pool = WebsitePage::where('crawl_site_id', $crawlSiteId)
            ->indexable()->whereNotNull('last_crawled_at')->where('http_status', 200)
            ->orderByDesc('inbound_link_count')
            ->limit(self::CANDIDATE_POOL)
            ->get(['id', 'title', 'url_hash', 'inbound_link_count', 'content_terms']);

        // Precompute each pool page's significant terms once (reused across targets).
        $poolTerms = [];
        foreach ($pool as $src) {
            $poolTerms[$src->id] = $this->termsFor($src, $df, $docs);
        }

        $now = now();
        $edges = [];
        foreach ($targets as $target) {
            $tt = $this->termsFor($target, $df, $docs);
            if ($tt === []) {
                continue;
            }
            $matches = [];
            foreach ($pool as $src) {
                if ($src->id === $target->id || $src->url_hash === $target->url_hash) {
                    continue;
                }
                $st = $poolTerms[$src->id] ?? [];
                $shared = array_intersect_key($tt, $st);
                if ($shared === []) {
                    continue;
                }
                // Overlap score = sum of the weaker weight per shared term.
                $score = 0.0;
                foreach ($shared as $term => $w) {
                    $score += min($tt[$term], $st[$term]);
                }
                $matches[] = ['id' => $src->id, 'score' => $score, 'authority' => (int) $src->inbound_link_count];
            }
            usort($matches, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $b['authority'] <=> $a['authority']);

            foreach (array_slice($matches, 0, self::SUGGESTIONS_PER_TARGET) as $m) {
                $edges[] = [
                    'crawl_site_id' => $crawlSiteId,
                    'from_page_id' => $m['id'],
                    'to_page_id' => $target->id,
                    'anchor_text' => mb_substr((string) ($target->title ?: $target->url), 0, 512),
                    'status' => WebsiteInternalLink::STATUS_SUGGESTED,
                    'discovered_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($edges, 500) as $chunk) {
            DB::table('website_internal_links')->insert($chunk);
        }

        return count($edges);
    }

    /**
     * Significant terms for a page (term => weight). Uses stored content_terms;
     * falls back to the title alone for pages not yet recrawled (pre-feature).
     *
     * @return array<string,float>
     */
    private function termsFor(WebsitePage $page, array $df, int $docs): array
    {
        $cand = json_decode((string) $page->content_terms, true);
        if (is_array($cand) && ! empty($cand['t'])) {
            return $this->extractor->significant($cand, $df, $docs);
        }

        // Fallback: title terms (so legacy pages still match until recrawled).
        $terms = [];
        foreach (TermExtractor::tokenize((string) $page->title) as $t) {
            $terms[$t] = 3.0;
        }

        return $terms;
    }
}
