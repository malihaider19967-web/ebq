<?php

namespace App\Services;

use App\Models\PageAuditReport;
use App\Models\SearchConsoleData;
use Illuminate\Support\Carbon;

class AuditPerformanceService
{
    /**
     * Pages with both an audit and live traffic where the audit performance
     * is weak — technical debt measurably costing traffic.
     *
     * @return list<array{
     *     page: string,
     *     audited_at: ?string,
     *     performance_score_mobile: ?int,
     *     performance_score_desktop: ?int,
     *     lcp_ms_mobile: ?int,
     *     cls_mobile: ?float,
     *     clicks: int,
     *     impressions: int,
     *     position: ?float,
     * }>
     */
    public function underperformingPages(int $websiteId, int $windowDays = 28, int $limit = 25, ?string $country = null): array
    {
        $tz = config('app.timezone');
        $end = Carbon::yesterday($tz)->endOfDay();
        $start = $end->copy()->subDays($windowDays - 1)->startOfDay();

        $audits = PageAuditReport::query()
            ->where('website_id', $websiteId)
            ->get(['page', 'page_hash', 'audited_at', 'result']);

        if ($audits->isEmpty()) {
            return [];
        }

        $pages = $audits->pluck('page')->unique()->values()->all();
        $traffic = SearchConsoleData::query()
            ->where('website_id', $websiteId)
            ->whereIn('page', $pages)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->when($country, fn ($q, $c) => $q->where('country', $c))
            ->selectRaw('page, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position')
            ->groupBy('page')
            ->get()
            ->keyBy('page');

        $out = [];
        foreach ($audits as $audit) {
            $t = $traffic->get($audit->page);
            $impressions = $t ? (int) $t->impressions : 0;
            if ($impressions < 100) {
                continue;
            }

            $cwv = is_array($audit->result) ? ($audit->result['core_web_vitals'] ?? []) : [];
            $mobile = is_array($cwv['mobile'] ?? null) ? $cwv['mobile'] : [];
            $desktop = is_array($cwv['desktop'] ?? null) ? $cwv['desktop'] : [];
            $mobileScore = isset($mobile['performance_score']) ? (int) $mobile['performance_score'] : null;
            $desktopScore = isset($desktop['performance_score']) ? (int) $desktop['performance_score'] : null;

            $worstScore = collect([$mobileScore, $desktopScore])->filter(fn ($v) => $v !== null)->min();
            if ($worstScore === null || $worstScore >= 70) {
                continue;
            }

            $out[] = [
                'page' => (string) $audit->page,
                'audited_at' => $audit->audited_at?->toDateTimeString(),
                'performance_score_mobile' => $mobileScore,
                'performance_score_desktop' => $desktopScore,
                'lcp_ms_mobile' => isset($mobile['lcp_ms']) ? (int) $mobile['lcp_ms'] : null,
                'cls_mobile' => isset($mobile['cls']) ? (float) $mobile['cls'] : null,
                'clicks' => $t ? (int) $t->clicks : 0,
                'impressions' => $impressions,
                'position' => $t && $t->position !== null ? round((float) $t->position, 1) : null,
            ];
        }

        usort($out, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);

        return array_slice($out, 0, $limit);
    }
}
