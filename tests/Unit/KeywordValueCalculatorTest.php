<?php

namespace Tests\Unit;

use App\Services\KeywordValueCalculator;
use PHPUnit\Framework\TestCase;

class KeywordValueCalculatorTest extends TestCase
{
    public function test_ctr_curve_returns_expected_values_for_top_positions(): void
    {
        $this->assertEqualsWithDelta(0.28, KeywordValueCalculator::ctrForPosition(1), 0.001);
        $this->assertEqualsWithDelta(0.11, KeywordValueCalculator::ctrForPosition(3), 0.001);
        $this->assertEqualsWithDelta(0.02, KeywordValueCalculator::ctrForPosition(10), 0.001);
    }

    public function test_ctr_curve_falls_off_past_page_one(): void
    {
        $this->assertEqualsWithDelta(0.01, KeywordValueCalculator::ctrForPosition(11), 0.001);
        $this->assertEqualsWithDelta(0.01, KeywordValueCalculator::ctrForPosition(20), 0.001);
        $this->assertEqualsWithDelta(0.005, KeywordValueCalculator::ctrForPosition(21), 0.001);
        $this->assertEqualsWithDelta(0.005, KeywordValueCalculator::ctrForPosition(100), 0.001);
    }

    public function test_ctr_for_position_is_null_safe(): void
    {
        $this->assertSame(0.0, KeywordValueCalculator::ctrForPosition(null));
        $this->assertSame(0.0, KeywordValueCalculator::ctrForPosition(0));
        $this->assertSame(0.0, KeywordValueCalculator::ctrForPosition(-5));
    }

    public function test_projected_monthly_clicks_rounds_to_int(): void
    {
        // 10000 × 0.11 = 1100
        $this->assertSame(1100, KeywordValueCalculator::projectedMonthlyClicks(10000, 3.0));
    }

    public function test_projected_monthly_clicks_is_zero_when_volume_missing(): void
    {
        $this->assertSame(0, KeywordValueCalculator::projectedMonthlyClicks(null, 3.0));
        $this->assertSame(0, KeywordValueCalculator::projectedMonthlyClicks(0, 3.0));
    }

    public function test_projected_monthly_value_returns_null_when_either_input_missing(): void
    {
        $this->assertNull(KeywordValueCalculator::projectedMonthlyValue(null, 3.0, 2.5));
        $this->assertNull(KeywordValueCalculator::projectedMonthlyValue(1000, 3.0, null));
        $this->assertNull(KeywordValueCalculator::projectedMonthlyValue(1000, 3.0, 0.0));
    }

    public function test_projected_monthly_value_multiplies_clicks_by_cpc(): void
    {
        // 10000 vol × 0.11 CTR @ pos 3 = 1100 clicks × $2.00 = $2200.00
        $this->assertEqualsWithDelta(2200.0, KeywordValueCalculator::projectedMonthlyValue(10000, 3.0, 2.0), 0.01);
    }

    public function test_upside_value_measures_gain_from_current_to_target(): void
    {
        // Current pos 12 → ctr 0.01 → 100 clicks × $5 = $500
        // Target pos 3 → ctr 0.11 → 1100 clicks × $5 = $5500
        // Upside = $5000
        $this->assertEqualsWithDelta(5000.0, KeywordValueCalculator::upsideValue(10000, 12.0, 3, 5.0), 0.01);
    }

    public function test_upside_value_clamps_negative_to_zero(): void
    {
        // Already at target position — no upside.
        $this->assertSame(0.0, KeywordValueCalculator::upsideValue(10000, 1.0, 3, 5.0));
    }

    public function test_upside_value_is_null_when_inputs_missing(): void
    {
        $this->assertNull(KeywordValueCalculator::upsideValue(null, 12.0, 3, 5.0));
        $this->assertNull(KeywordValueCalculator::upsideValue(10000, 12.0, 3, null));
    }

    public function test_trend_classify_detects_rising(): void
    {
        $trend = $this->trend([100, 110, 125, 140, 160, 180, 220, 260, 310, 370, 440, 520]);
        $this->assertSame('rising', KeywordValueCalculator::trendClassify($trend));
    }

    public function test_trend_classify_detects_falling(): void
    {
        $trend = $this->trend([520, 440, 370, 310, 260, 220, 180, 160, 140, 125, 110, 100]);
        $this->assertSame('falling', KeywordValueCalculator::trendClassify($trend));
    }

    public function test_trend_classify_detects_seasonal(): void
    {
        // High amplitude, low net slope over the last 6 months — classic holiday spike.
        $trend = $this->trend([100, 120, 150, 220, 400, 800, 400, 200, 120, 110, 105, 100]);
        $this->assertSame('seasonal', KeywordValueCalculator::trendClassify($trend));
    }

    public function test_trend_classify_detects_stable(): void
    {
        $trend = $this->trend([1000, 1020, 990, 1010, 1005, 1015, 995, 1000, 1005, 1010, 990, 1000]);
        $this->assertSame('stable', KeywordValueCalculator::trendClassify($trend));
    }

    public function test_trend_classify_unknown_on_empty_or_short(): void
    {
        $this->assertSame('unknown', KeywordValueCalculator::trendClassify(null));
        $this->assertSame('unknown', KeywordValueCalculator::trendClassify([]));
        $this->assertSame('unknown', KeywordValueCalculator::trendClassify($this->trend([1, 2, 3])));
    }

    public function test_next_peak_month_picks_the_max_when_above_mean(): void
    {
        $trend = $this->trend([100, 120, 150, 220, 400, 800, 400, 200, 120, 110, 105, 100]);
        $this->assertSame(6, KeywordValueCalculator::nextPeakMonth($trend)); // June
    }

    public function test_next_peak_month_null_on_flat_series(): void
    {
        $trend = $this->trend([1000, 1020, 990, 1010, 1005, 1015, 995, 1000, 1005, 1010, 990, 1000]);
        $this->assertNull(KeywordValueCalculator::nextPeakMonth($trend));
    }

    /**
     * Convenience: build a KE-shaped trend array from a list of 12 monthly values,
     * spanning from last-year same-month through to the current month.
     *
     * @param  list<int|float>  $values
     * @return list<array<string, mixed>>
     */
    private function trend(array $values): array
    {
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $year = 2025;
        $m = 1;
        $out = [];
        foreach ($values as $v) {
            $out[] = ['month' => $months[$m - 1], 'year' => $year, 'value' => $v];
            $m++;
            if ($m > 12) {
                $m = 1;
                $year++;
            }
        }

        return $out;
    }
}
