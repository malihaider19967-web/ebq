<?php

namespace App\Support\Audit;

use App\Models\PageAuditReport;

/**
 * Compact audit metrics for list/history UIs (HQ plugin, APIs).
 * Score formula matches {@see resources/views/livewire/pages/partials/audit-report.blade.php}.
 */
final class PageAuditReportSummary
{
    /**
     * @return array{
     *     score: int,
     *     score_tone: string,
     *     score_label: string,
     *     issues: array{critical: int, warning: int, serp_gap: int, info: int, good: int},
     *     top_issue: string|null,
     *     word_count: int|null,
     *     http_status: int|null,
     *     response_time_ms: int|null,
     *     keyword_coverage_pct: float|null,
     *     performance_score_mobile: int|null,
     *     market_label: string|null,
     *     serp_competitors: int|null,
     * }|null
     */
    public static function fromReport(?PageAuditReport $report): ?array
    {
        if ($report === null || $report->status !== 'completed') {
            return null;
        }

        $result = is_array($report->result) ? $report->result : [];
        $recs = is_array($result['recommendations'] ?? null) ? $result['recommendations'] : [];

        $counts = collect($recs)->groupBy('severity')->map->count();
        $issues = [
            'critical' => (int) ($counts['critical'] ?? 0),
            'warning' => (int) ($counts['warning'] ?? 0),
            'serp_gap' => (int) ($counts['serp_gap'] ?? 0),
            'info' => (int) ($counts['info'] ?? 0),
            'good' => (int) ($counts['good'] ?? 0),
        ];

        $score = max(
            0,
            100
            - ($issues['critical'] * 15)
            - ($issues['warning'] * 6)
            - ($issues['serp_gap'] * 5)
            - ($issues['info'] * 2),
        );

        $scoreTone = $score >= 85 ? 'good' : ($score >= 65 ? 'warn' : 'bad');
        $scoreLabel = $score >= 85 ? 'Healthy' : ($score >= 65 ? 'Needs attention' : 'Critical');

        $content = is_array($result['content'] ?? null) ? $result['content'] : [];
        $wordCount = isset($content['word_count']) ? (int) $content['word_count'] : null;
        if ($wordCount !== null && $wordCount <= 0) {
            $wordCount = null;
        }

        $kwBlock = is_array($result['keywords'] ?? null) ? $result['keywords'] : [];
        $coverage = is_array($kwBlock['coverage'] ?? null) ? $kwBlock['coverage'] : [];
        $keywordCoverage = isset($coverage['score']) ? (float) $coverage['score'] : null;
        if ($keywordCoverage !== null && ($kwBlock['available'] ?? false) !== true) {
            $keywordCoverage = null;
        }

        $cwv = is_array($result['core_web_vitals'] ?? null) ? $result['core_web_vitals'] : [];
        $mobile = is_array($cwv['mobile'] ?? null) ? $cwv['mobile'] : [];
        $perfMobile = isset($mobile['performance_score']) ? (int) $mobile['performance_score'] : null;

        $benchmark = is_array($result['benchmark'] ?? null) ? $result['benchmark'] : null;
        $competitors = is_array($benchmark) ? count($benchmark['competitors'] ?? []) : null;
        if ($competitors === 0) {
            $competitors = null;
        }

        $pageLocale = is_array($result['page_locale'] ?? null) ? $result['page_locale'] : null;
        $marketLabel = PageLocalePresentation::shortLabel($pageLocale);

        return [
            'score' => $score,
            'score_tone' => $scoreTone,
            'score_label' => $scoreLabel,
            'issues' => $issues,
            'top_issue' => self::topIssueTitle($recs),
            'word_count' => $wordCount,
            'http_status' => $report->http_status !== null ? (int) $report->http_status : null,
            'response_time_ms' => $report->response_time_ms !== null ? (int) $report->response_time_ms : null,
            'keyword_coverage_pct' => $keywordCoverage,
            'performance_score_mobile' => $perfMobile,
            'market_label' => $marketLabel,
            'serp_competitors' => $competitors,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $recs
     */
    private static function topIssueTitle(array $recs): ?string
    {
        $priority = [
            RecommendationEngine::SEV_CRITICAL,
            RecommendationEngine::SEV_WARNING,
            RecommendationEngine::SEV_SERP_GAP,
            RecommendationEngine::SEV_INFO,
        ];

        foreach ($priority as $severity) {
            foreach ($recs as $rec) {
                if (! is_array($rec) || ($rec['severity'] ?? '') !== $severity) {
                    continue;
                }
                $title = trim((string) ($rec['title'] ?? ''));
                if ($title !== '') {
                    return mb_substr($title, 0, 120);
                }
            }
        }

        return null;
    }
}
