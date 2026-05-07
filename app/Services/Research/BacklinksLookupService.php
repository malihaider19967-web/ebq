<?php

namespace App\Services\Research;

use App\Models\Research\CompetitorOutlink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cross-scan inverted-index over `competitor_outlinks`. When we scraped
 * site A and recorded that page A.com/x linked to B.com/y, that's a
 * backlink for B — we just stored it from A's perspective. This service
 * answers "show me every linking page across all our scans where
 * to_domain = X" plus the standard SEMrush/Ahrefs slice-and-dice
 * (referring-domain count, anchor-text distribution, top linking
 * pages).
 */
class BacklinksLookupService
{
    /**
     * Top linking pages pointing at $targetDomain.
     *
     * @return Collection<int, object{from_domain:string, from_url:string, anchor_text:?string, scan_id:int, scanned_at:?\Illuminate\Support\Carbon}>
     */
    public function linkingPages(string $targetDomain, int $limit = 100): Collection
    {
        $domain = $this->normalize($targetDomain);
        if ($domain === '') {
            return collect();
        }

        return DB::table('competitor_outlinks as o')
            ->join('competitor_pages as p', 'p.id', '=', 'o.from_page_id')
            ->join('competitor_scans as s', 's.id', '=', 'o.competitor_scan_id')
            ->where('o.to_domain', $domain)
            ->where('o.is_external', true)
            ->orderByDesc('s.finished_at')
            ->limit($limit)
            ->select([
                'p.domain as from_domain',
                'p.url as from_url',
                'p.title as from_title',
                'o.to_url',
                'o.anchor_text',
                'o.competitor_scan_id as scan_id',
                's.seed_domain as scan_seed',
                's.finished_at as scanned_at',
            ])
            ->get();
    }

    /**
     * Count of distinct domains linking to $targetDomain — a free DR/DA
     * proxy across our corpus. The bigger this gets as we scan more
     * sites, the more useful the metric becomes.
     */
    public function referringDomainCount(string $targetDomain): int
    {
        $domain = $this->normalize($targetDomain);
        if ($domain === '') {
            return 0;
        }

        return (int) DB::table('competitor_outlinks as o')
            ->join('competitor_pages as p', 'p.id', '=', 'o.from_page_id')
            ->where('o.to_domain', $domain)
            ->where('o.is_external', true)
            ->where('p.domain', '!=', $domain)
            ->distinct('p.domain')
            ->count('p.domain');
    }

    /**
     * Anchor-text distribution. Useful for spotting branded vs
     * commercial vs partial-match link profiles.
     *
     * @return Collection<int, object{anchor_text:string, link_count:int}>
     */
    public function anchorTextDistribution(string $targetDomain, int $limit = 25): Collection
    {
        $domain = $this->normalize($targetDomain);
        if ($domain === '') {
            return collect();
        }

        return DB::table('competitor_outlinks')
            ->where('to_domain', $domain)
            ->where('is_external', true)
            ->whereNotNull('anchor_text')
            ->where('anchor_text', '!=', '')
            ->select(DB::raw('LOWER(anchor_text) as anchor_text'), DB::raw('COUNT(*) as link_count'))
            ->groupBy(DB::raw('LOWER(anchor_text)'))
            ->orderByDesc('link_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Aggregate summary returned to UI in one call.
     *
     * @return array{
     *   target_domain:string,
     *   total_links:int,
     *   referring_domains:int,
     *   linking_pages:Collection<int, object>,
     *   anchors:Collection<int, object>,
     * }
     */
    public function summary(string $targetDomain, int $linkLimit = 50, int $anchorLimit = 15): array
    {
        $domain = $this->normalize($targetDomain);

        return [
            'target_domain' => $domain,
            'total_links' => CompetitorOutlink::query()
                ->where('to_domain', $domain)
                ->where('is_external', true)
                ->count(),
            'referring_domains' => $this->referringDomainCount($domain),
            'linking_pages' => $this->linkingPages($domain, $linkLimit),
            'anchors' => $this->anchorTextDistribution($domain, $anchorLimit),
        ];
    }

    private function normalize(string $domain): string
    {
        $host = parse_url(trim($domain), PHP_URL_HOST) ?: $domain;
        $host = mb_strtolower(trim($host));
        return preg_replace('/^www\./', '', $host) ?: $host;
    }
}
