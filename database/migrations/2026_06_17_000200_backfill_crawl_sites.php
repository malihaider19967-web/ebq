<?php

use App\Models\CrawlSite;
use App\Models\Website;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Link every existing website to a shared crawl_site (one per normalized domain)
 * and move the domain-level crawl signals (crawl_protection + sitemap-lastmod
 * trust counters) off `websites` onto the crawl_site. Crawl DATA was cleared in
 * the prior migration and regenerates on the next crawl, so there is nothing to
 * stamp on the crawl tables here. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $byDomain = [];

        Website::query()->orderBy('id')->each(function (Website $w) use (&$byDomain): void {
            $domain = CrawlSite::normalizeDomain((string) $w->domain);
            if ($domain === '') {
                return; // placeholder / sourceless row — linked when it gets a real domain
            }

            $site = $byDomain[$domain]
                ??= CrawlSite::firstOrCreate(['normalized_domain' => $domain]);

            DB::table('websites')->where('id', $w->id)->update([
                'crawl_site_id' => $site->id,
                'normalized_domain' => $domain,
            ]);

            // Carry domain-level signals up to the crawl_site (max counters / any protection).
            $site->forceFill([
                'sitemap_lastmod_true' => $site->sitemap_lastmod_true + (int) ($w->sitemap_lastmod_true ?? 0),
                'sitemap_lastmod_false' => $site->sitemap_lastmod_false + (int) ($w->sitemap_lastmod_false ?? 0),
                'crawl_protection' => $site->crawl_protection ?? $w->crawl_protection,
                'crawl_protection_at' => $site->crawl_protection_at ?? $w->crawl_protection_at,
            ])->save();
        });

        // Effective cap = max page cap among each site's subscribers.
        foreach (CrawlSite::query()->withCount('websites')->get() as $site) {
            $cap = $site->websites()->get()->map(fn (Website $w): int => $w->crawlPageCap())->max() ?? 0;
            $site->forceFill([
                'effective_cap' => (int) $cap,
                'subscriber_count' => (int) $site->websites_count,
            ])->save();
        }
    }

    public function down(): void
    {
        DB::table('websites')->update(['crawl_site_id' => null]);
    }
};
