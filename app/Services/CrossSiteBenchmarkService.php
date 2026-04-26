<?php

namespace App\Services;

use App\Models\SearchConsoleData;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 #7 — Cross-site anonymized benchmarks.
 *
 * Aggregates GSC averages across the entire EBQ network and reports
 * "your site vs peers" stats. Single-site competitors physically can't
 * match this — they only have data for one site each. Network-effect MOAT.
 *
 * Privacy: only aggregate stats are exposed (avg, p50, p90 across N sites,
 * site-id list never crosses tenant boundaries). Minimum cohort size 5
 * — when fewer sites are eligible we report "industry sample too small"
 * rather than risk individual identification.
 *
 * Industry segmentation: derived from the website's GSC top queries
 * (TLD heuristics + a small Vertical map could be added later). For
 * v1 we use a single GLOBAL cohort + a per-COUNTRY cohort. Vertical
 * tagging is the obvious next step.
 *
 * Caching: 24h. Cross-site math is expensive enough (full SearchConsoleData
 * scan) and the result is stable across a day.
 */
class CrossSiteBenchmarkService
{
    private const CACHE_TTL_HOURS = 24;
    private const MIN_COHORT_SIZE = 5;

    /**
     * @return array{
     *   ok: bool,
     *   reason?: string,
     *   your: array{avg_position: float|null, ctr_pct: float|null, queries_30d: int, clicks_30d: int},
     *   global: array{avg_position: float|null, ctr_pct: float|null, p50_position: float|null, p90_position: float|null, sample_size: int},
     *   country?: array{country: string, avg_position: float|null, ctr_pct: float|null, sample_size: int},
     *   percentile?: int,  // your position vs the global cohort, 0-100 (higher = better rank)
     * }
     */
    public function forWebsite(Website $website, ?string $country = null): array
    {
        $cacheKey = sprintf(
            'ebq_xsite_benchmark:%d:%s',
            $website->id,
            strtolower((string) $country ?: 'global'),
        );
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz);
        $start30 = $end->copy()->subDays(29);

        // ── YOUR site stats
        $yours = SearchConsoleData::query()
            ->where('website_id', $website->id)
            ->whereDate('date', '>=', $start30->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('AVG(position) AS avg_pos, AVG(ctr) AS avg_ctr, COUNT(DISTINCT query) AS queries, SUM(clicks) AS clicks')
            ->first();

        $yourPayload = [
            'avg_position' => $yours && $yours->avg_pos !== null ? round((float) $yours->avg_pos, 2) : null,
            'ctr_pct' => $yours && $yours->avg_ctr !== null ? round((float) $yours->avg_ctr * 100, 3) : null,
            'queries_30d' => (int) ($yours->queries ?? 0),
            'clicks_30d' => (int) ($yours->clicks ?? 0),
        ];

        // ── GLOBAL cohort: per-website aggregates → cross-site stats.
        $perSite = SearchConsoleData::query()
            ->whereDate('date', '>=', $start30->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('website_id, AVG(position) AS avg_pos, AVG(ctr) AS avg_ctr')
            ->groupBy('website_id')
            ->havingRaw('COUNT(*) >= 100') // exclude empty sites
            ->get();

        if ($perSite->count() < self::MIN_COHORT_SIZE) {
            $result = [
                'ok' => false,
                'reason' => 'cohort_too_small',
                'your' => $yourPayload,
                'global' => ['avg_position' => null, 'ctr_pct' => null, 'p50_position' => null, 'p90_position' => null, 'sample_size' => $perSite->count()],
            ];
            Cache::put($cacheKey, $result, Carbon::now()->addHours(self::CACHE_TTL_HOURS));
            return $result;
        }

        $positions = $perSite->pluck('avg_pos')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->values()->all();
        $ctrs = $perSite->pluck('avg_ctr')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->values()->all();

        sort($positions);
        $globalPayload = [
            'avg_position' => $positions !== [] ? round(array_sum($positions) / count($positions), 2) : null,
            'ctr_pct' => $ctrs !== [] ? round((array_sum($ctrs) / count($ctrs)) * 100, 3) : null,
            'p50_position' => $positions !== [] ? round($this->percentile($positions, 50), 2) : null,
            'p90_position' => $positions !== [] ? round($this->percentile($positions, 90), 2) : null,
            'sample_size' => count($positions),
        ];

        // ── Where YOUR avg position falls in the global distribution.
        // Lower numerical position = better rank; convert to 0..100 percentile
        // where higher = better.
        $percentile = null;
        if ($yourPayload['avg_position'] !== null && $positions !== []) {
            $worseThanYou = 0;
            foreach ($positions as $p) {
                if ($p > $yourPayload['avg_position']) $worseThanYou++;
            }
            $percentile = (int) round(($worseThanYou / count($positions)) * 100);
        }

        $result = [
            'ok' => true,
            'your' => $yourPayload,
            'global' => $globalPayload,
            'percentile' => $percentile,
        ];

        // ── Optional country cohort.
        if ($country !== null && $country !== '') {
            $countryStats = $this->countryCohort($start30, $end, strtolower($country));
            if ($countryStats !== null) {
                $result['country'] = $countryStats;
            }
        }

        Cache::put($cacheKey, $result, Carbon::now()->addHours(self::CACHE_TTL_HOURS));
        return $result;
    }

    /**
     * @return array{country: string, avg_position: float|null, ctr_pct: float|null, sample_size: int}|null
     */
    private function countryCohort(Carbon $start, Carbon $end, string $country): ?array
    {
        $perSite = SearchConsoleData::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('country', $country)
            ->selectRaw('website_id, AVG(position) AS avg_pos, AVG(ctr) AS avg_ctr')
            ->groupBy('website_id')
            ->havingRaw('COUNT(*) >= 50')
            ->get();

        if ($perSite->count() < self::MIN_COHORT_SIZE) {
            return null;
        }

        $positions = $perSite->pluck('avg_pos')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->values()->all();
        $ctrs = $perSite->pluck('avg_ctr')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->values()->all();

        return [
            'country' => $country,
            'avg_position' => $positions !== [] ? round(array_sum($positions) / count($positions), 2) : null,
            'ctr_pct' => $ctrs !== [] ? round((array_sum($ctrs) / count($ctrs)) * 100, 3) : null,
            'sample_size' => count($positions),
        ];
    }

    /**
     * @param  list<float>  $sortedAscending
     */
    private function percentile(array $sortedAscending, int $p): float
    {
        if ($sortedAscending === []) return 0.0;
        $rank = max(0, min(count($sortedAscending) - 1, (int) floor((count($sortedAscending) - 1) * $p / 100)));
        return $sortedAscending[$rank];
    }
}
