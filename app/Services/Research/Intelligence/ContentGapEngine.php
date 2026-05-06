<?php

namespace App\Services\Research\Intelligence;

use App\Models\Research\Keyword;
use App\Models\Research\SerpResult;
use App\Models\Research\WebsitePageKeyword;
use App\Models\Website;
use Illuminate\Support\Collection;

/**
 * Returns keywords competitor domains rank for that the focal website does
 * not. Set difference over (serp_results domain X keyword) ∖ (the site's
 * known keywords from WebsitePageKeyword).
 */
class ContentGapEngine
{
    /**
     * @param  list<string>  $competitorDomains
     * @return Collection<int, Keyword>
     */
    public function missingKeywords(Website $website, array $competitorDomains, int $limit = 100): Collection
    {
        $competitorDomains = array_values(array_filter(array_map(
            fn ($d) => mb_strtolower(trim((string) $d)),
            $competitorDomains
        )));
        if ($competitorDomains === []) {
            return collect();
        }

        $siteKeywordIds = WebsitePageKeyword::query()
            ->whereIn('page_id', function ($sub) use ($website) {
                $sub->select('id')->from('website_pages')->where('website_id', $website->id);
            })
            ->pluck('keyword_id')
            ->all();

        $competitorKeywordIds = SerpResult::query()
            ->whereIn('domain', $competitorDomains)
            ->whereIn('snapshot_id', function ($sub) {
                $sub->select('id')->from('serp_snapshots');
            })
            ->whereHas('snapshot')
            ->with('snapshot:id,keyword_id')
            ->limit(5000)
            ->get()
            ->pluck('snapshot.keyword_id')
            ->filter()
            ->unique()
            ->all();

        $missingIds = array_values(array_diff($competitorKeywordIds, $siteKeywordIds));
        if ($missingIds === []) {
            return collect();
        }

        return Keyword::query()
            ->whereIn('id', array_slice($missingIds, 0, $limit))
            ->get();
    }
}
