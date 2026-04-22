<?php

namespace App\Services;

/**
 * Pure, stateless calculators that translate Keywords Everywhere metrics
 * into money and time-aware signals. Every view, service, and report that
 * needs "what's this keyword worth?" or "is this trend rising?" goes
 * through here so the formulas live in exactly one place.
 *
 * Inputs are intentionally null-tolerant — most surfaces feed in possibly-
 * missing GSC positions or missing KE data. Null in → sensible zero/null
 * out, never a thrown exception.
 */
class KeywordValueCalculator
{
    /**
     * Rounded Sistrix-style SERP CTR curve. Numbers aren't gospel — they're
     * an industry-accepted approximation we use everywhere for consistency.
     *
     * Position 1 captures ~28%, position 10 ~2%, beyond-20 near-zero.
     */
    private const CTR_CURVE = [
        1 => 0.28,
        2 => 0.15,
        3 => 0.11,
        4 => 0.08,
        5 => 0.07,
        6 => 0.05,
        7 => 0.04,
        8 => 0.03,
        9 => 0.025,
        10 => 0.02,
    ];

    public static function ctrForPosition(?float $position): float
    {
        if ($position === null || $position <= 0) {
            return 0.0;
        }

        $p = (int) round($position);
        if ($p <= 0) {
            return 0.0;
        }
        if (isset(self::CTR_CURVE[$p])) {
            return self::CTR_CURVE[$p];
        }
        if ($p <= 20) {
            return 0.01;
        }

        return 0.005;
    }

    public static function projectedMonthlyClicks(?int $volume, ?float $position): int
    {
        if ($volume === null || $volume <= 0) {
            return 0;
        }

        return (int) round($volume * self::ctrForPosition($position));
    }

    /**
     * Projected organic-click value at the given position. Null-safe —
     * returns null when we don't have volume or cpc yet (so the UI can
     * render a dash instead of a misleading "$0").
     */
    public static function projectedMonthlyValue(?int $volume, ?float $position, ?float $cpc): ?float
    {
        if ($volume === null || $volume <= 0 || $cpc === null || $cpc <= 0) {
            return null;
        }

        $clicks = self::projectedMonthlyClicks($volume, $position);

        return round($clicks * $cpc, 2);
    }

    /**
     * The dollar upside of moving from `currentPos` to `targetPos` at
     * the current CPC. Used by striking-distance sort + quick-wins scoring.
     * Returns null when data is missing so callers can fall through to
     * a legacy ranking signal.
     */
    public static function upsideValue(?int $volume, ?float $currentPos, int $targetPos, ?float $cpc): ?float
    {
        if ($volume === null || $volume <= 0 || $cpc === null || $cpc <= 0) {
            return null;
        }

        $current = self::projectedMonthlyValue($volume, $currentPos ?? 100.0, $cpc) ?? 0.0;
        $target = self::projectedMonthlyValue($volume, (float) $targetPos, $cpc) ?? 0.0;

        return max(0.0, round($target - $current, 2));
    }

    /**
     * Classify a 12-month search-volume trend array as one of:
     *   - 'rising'    — log-slope of last 6 months > +0.08
     *   - 'falling'   — log-slope < -0.08
     *   - 'seasonal'  — high coefficient-of-variation without a monotonic slope
     *   - 'stable'    — we have trend data and it's flat
     *   - 'unknown'   — no trend, or too few points to judge
     *
     * Input shape (per KE response): list of `['month' => 'January', 'year' => 2026, 'value' => 5400]`.
     */
    public static function trendClassify(?array $trend12m): string
    {
        $values = self::extractTrendValues($trend12m);
        if (count($values) < 6) {
            return 'unknown';
        }

        // Seasonality wins before slope: a series that peaks in June and
        // troughs in December should read as 'seasonal' (we expect the
        // rebound next year), not 'falling' just because the last 6 months
        // point down.
        $mean = array_sum($values) / count($values);
        if ($mean <= 0) {
            return 'unknown';
        }
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) ** 2;
        }
        $stddev = sqrt($variance / count($values));
        $cv = $stddev / $mean;

        if ($cv > 0.6) {
            return 'seasonal';
        }

        $last6 = array_slice($values, -6);
        $slope = self::logSlope($last6);

        if ($slope > 0.08) {
            return 'rising';
        }
        if ($slope < -0.08) {
            return 'falling';
        }

        return 'stable';
    }

    /**
     * Month-of-year (1–12) where the trend historically peaks. Returns null
     * when the series is flat (within 10% of the mean) or too short.
     */
    public static function nextPeakMonth(?array $trend12m): ?int
    {
        if (! is_array($trend12m) || $trend12m === []) {
            return null;
        }

        $monthNames = [
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
            'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
            'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        ];

        $peak = null;
        $peakValue = PHP_FLOAT_MIN;
        $values = [];
        foreach ($trend12m as $row) {
            if (! is_array($row)) {
                continue;
            }
            $value = isset($row['value']) && is_numeric($row['value']) ? (float) $row['value'] : null;
            $monthKey = isset($row['month']) && is_string($row['month']) ? strtolower(trim($row['month'])) : '';
            $monthNum = $monthNames[$monthKey] ?? null;
            if ($value === null || $monthNum === null) {
                continue;
            }
            $values[] = $value;
            if ($value > $peakValue) {
                $peakValue = $value;
                $peak = $monthNum;
            }
        }

        if ($peak === null || $values === []) {
            return null;
        }

        $mean = array_sum($values) / count($values);
        if ($mean <= 0 || $peakValue <= $mean * 1.1) {
            return null;
        }

        return $peak;
    }

    /**
     * @return list<float>
     */
    private static function extractTrendValues(?array $trend12m): array
    {
        if (! is_array($trend12m)) {
            return [];
        }

        $values = [];
        foreach ($trend12m as $row) {
            if (! is_array($row) || ! isset($row['value']) || ! is_numeric($row['value'])) {
                continue;
            }
            $values[] = (float) $row['value'];
        }

        return $values;
    }

    /**
     * Simple log-linear slope fit. Avoids log(0) by adding 1 before log.
     * Returns slope per month (so +0.1 ≈ +10% MoM growth in log space).
     *
     * @param  list<float>  $values
     */
    private static function logSlope(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $xSum = 0.0;
        $ySum = 0.0;
        $xySum = 0.0;
        $x2Sum = 0.0;

        foreach ($values as $i => $v) {
            $x = (float) $i;
            $y = log(max(1.0, $v) + 1.0);
            $xSum += $x;
            $ySum += $y;
            $xySum += $x * $y;
            $x2Sum += $x * $x;
        }

        $denom = $n * $x2Sum - $xSum * $xSum;
        if ($denom == 0.0) {
            return 0.0;
        }

        return ($n * $xySum - $xSum * $ySum) / $denom;
    }
}
