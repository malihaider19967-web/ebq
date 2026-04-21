<?php

namespace App\Services;

use App\Models\RankTrackingKeyword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TrafficAnomalyDetector
{
    /**
     * Detect a single-day anomaly on {clicks, sessions, avg tracked-keyword position}
     * by comparing yesterday's value against a baseline computed from the prior 28 days.
     *
     * Signals fire when a metric deviates from baseline by both:
     *   • a fixed relative threshold (prevents noise on tiny-volume sites), AND
     *   • ≥ 2 standard deviations below the baseline (prevents false positives
     *     on sites with naturally volatile traffic).
     *
     * @return array{
     *     has_anomaly: bool,
     *     metrics: array<string, array{
     *         current: float,
     *         baseline_mean: float,
     *         baseline_stddev: float,
     *         z_score: ?float,
     *         change_percent: ?float,
     *         triggered: bool,
     *         higher_is_better: bool,
     *     }>,
     *     date: string,
     * }
     */
    public function detect(int $websiteId): array
    {
        $tz = config('app.timezone');
        $target = Carbon::yesterday($tz);
        $baselineEnd = $target->copy()->subDay();
        $baselineStart = $baselineEnd->copy()->subDays(27);

        $clicks = $this->analyzeClicks($websiteId, $target, $baselineStart, $baselineEnd);
        $sessions = $this->analyzeSessions($websiteId, $target, $baselineStart, $baselineEnd);
        $rankPos = $this->analyzeRank($websiteId, $target, $baselineStart, $baselineEnd);

        $metrics = [
            'clicks' => $clicks,
            'sessions' => $sessions,
            'avg_rank_position' => $rankPos,
        ];

        $hasAnomaly = (bool) collect($metrics)->firstWhere('triggered', true);

        return [
            'has_anomaly' => $hasAnomaly,
            'metrics' => $metrics,
            'date' => $target->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function analyzeClicks(int $websiteId, Carbon $target, Carbon $baselineStart, Carbon $baselineEnd): array
    {
        $series = DB::table('search_console_data')
            ->where('website_id', $websiteId)
            ->whereDate('date', '>=', $baselineStart->toDateString())
            ->whereDate('date', '<=', $target->toDateString())
            ->selectRaw('DATE(date) as d, SUM(clicks) as v')
            ->groupBy('d')
            ->pluck('v', 'd');

        return $this->scoreSeries($series, $target, higherIsBetter: true, relativeDrop: 0.35, minBaseline: 50.0);
    }

    /** @return array<string, mixed> */
    private function analyzeSessions(int $websiteId, Carbon $target, Carbon $baselineStart, Carbon $baselineEnd): array
    {
        $series = DB::table('analytics_data')
            ->where('website_id', $websiteId)
            ->whereDate('date', '>=', $baselineStart->toDateString())
            ->whereDate('date', '<=', $target->toDateString())
            ->selectRaw('DATE(date) as d, SUM(sessions) as v')
            ->groupBy('d')
            ->pluck('v', 'd');

        return $this->scoreSeries($series, $target, higherIsBetter: true, relativeDrop: 0.35, minBaseline: 50.0);
    }

    /** @return array<string, mixed> */
    private function analyzeRank(int $websiteId, Carbon $target, Carbon $baselineStart, Carbon $baselineEnd): array
    {
        $keywords = RankTrackingKeyword::query()
            ->where('website_id', $websiteId)
            ->where('is_active', true)
            ->pluck('id');

        if ($keywords->isEmpty()) {
            return $this->emptyMetric(higherIsBetter: false);
        }

        $rows = DB::table('rank_tracking_snapshots')
            ->whereIn('rank_tracking_keyword_id', $keywords)
            ->whereNotNull('position')
            ->whereBetween('checked_at', [$baselineStart->copy()->startOfDay(), $target->copy()->endOfDay()])
            ->selectRaw('DATE(checked_at) as d, AVG(position) as v')
            ->groupBy('d')
            ->pluck('v', 'd');

        return $this->scoreSeries($rows, $target, higherIsBetter: false, relativeDrop: 0.25, minBaseline: 1.0);
    }

    private function scoreSeries(
        \Illuminate\Support\Collection $series,
        Carbon $target,
        bool $higherIsBetter,
        float $relativeDrop,
        float $minBaseline,
    ): array {
        $targetKey = $target->toDateString();
        $current = $series->has($targetKey) ? (float) $series->get($targetKey) : null;
        $baseline = $series->except([$targetKey])->values()->map(fn ($v) => (float) $v);

        if ($current === null || $baseline->count() < 7) {
            return $this->emptyMetric($higherIsBetter) + ['current' => $current ?? 0.0];
        }

        $mean = $baseline->avg();
        if ($mean < $minBaseline) {
            return [
                'current' => $current,
                'baseline_mean' => round($mean, 2),
                'baseline_stddev' => 0.0,
                'z_score' => null,
                'change_percent' => null,
                'triggered' => false,
                'higher_is_better' => $higherIsBetter,
            ];
        }

        $variance = $baseline->reduce(fn ($carry, $v) => $carry + (($v - $mean) ** 2), 0.0) / $baseline->count();
        $stddev = sqrt($variance);
        $z = $stddev > 0 ? ($current - $mean) / $stddev : null;
        $pct = $mean > 0 ? (($current - $mean) / $mean) * 100 : null;

        // Require both the relative threshold AND z >= 2σ — unless the baseline has
        // zero variance (a perfectly flat series), in which case relative-only is
        // the best signal available.
        $meetsRelative = $higherIsBetter
            ? ($pct !== null && $pct <= -($relativeDrop * 100))
            : ($pct !== null && $pct >= ($relativeDrop * 100));
        $meetsZ = $z === null
            ? $stddev == 0.0
            : ($higherIsBetter ? $z <= -2.0 : $z >= 2.0);
        $triggered = $meetsRelative && $meetsZ;

        return [
            'current' => round($current, 2),
            'baseline_mean' => round($mean, 2),
            'baseline_stddev' => round($stddev, 2),
            'z_score' => $z !== null ? round($z, 2) : null,
            'change_percent' => $pct !== null ? round($pct, 1) : null,
            'triggered' => $triggered,
            'higher_is_better' => $higherIsBetter,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyMetric(bool $higherIsBetter): array
    {
        return [
            'current' => 0.0,
            'baseline_mean' => 0.0,
            'baseline_stddev' => 0.0,
            'z_score' => null,
            'change_percent' => null,
            'triggered' => false,
            'higher_is_better' => $higherIsBetter,
        ];
    }
}
