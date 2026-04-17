<?php

namespace Tests\Unit;

use App\Support\Audit\KeywordStrategyAnalyzer;
use PHPUnit\Framework\TestCase;

class KeywordStrategyAnalyzerTest extends TestCase
{
    private function components(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Page',
            'meta_description' => 'Desc',
            'h1_text' => 'Heading',
            'all_headings_text' => '',
            'body_text' => 'body text',
            'keyword_density' => [],
        ], $overrides);
    }

    /** @return array{query: string, clicks: int, impressions: int} */
    private function kw(string $query, int $clicks = 1, int $impressions = 10): array
    {
        return ['query' => $query, 'clicks' => $clicks, 'impressions' => $impressions];
    }

    public function test_how_to_fix_counts_support_not_informational_how_to(): void
    {
        $analyzer = new KeywordStrategyAnalyzer;
        $intent = $analyzer->analyze([$this->kw('how to fix dns error')], $this->components())['intent'];

        $this->assertGreaterThanOrEqual(1, $intent['support_count']);
        $this->assertSame(0, $intent['informational_count']);
    }

    public function test_how_to_use_skips_informational_how_to(): void
    {
        $analyzer = new KeywordStrategyAnalyzer;
        $intent = $analyzer->analyze([$this->kw('how to use kubernetes dashboard')], $this->components())['intent'];

        $this->assertGreaterThanOrEqual(1, $intent['support_count']);
        $this->assertSame(0, $intent['informational_count']);
    }

    public function test_free_trial_is_transactional_not_utility_free(): void
    {
        $analyzer = new KeywordStrategyAnalyzer;
        $intent = $analyzer->analyze([$this->kw('start free trial no credit card')], $this->components())['intent'];

        $this->assertGreaterThanOrEqual(1, $intent['transactional_count']);
        $this->assertSame(0, $intent['utility_count']);
    }

    public function test_lone_free_matches_utility(): void
    {
        $analyzer = new KeywordStrategyAnalyzer;
        $intent = $analyzer->analyze([$this->kw('free icon pack download')], $this->components())['intent'];

        $this->assertGreaterThanOrEqual(1, $intent['utility_count']);
    }

    public function test_dominant_compound_when_two_buckets_tie_top_weighted_score(): void
    {
        $analyzer = new KeywordStrategyAnalyzer;
        $keywords = [
            $this->kw('alpha vs beta', 1, 10),
            $this->kw('gamma vs delta', 1, 10),
            $this->kw('epsilon vs zeta', 1, 10),
            $this->kw('what is dns', 1, 10),
            $this->kw('what is smtp', 1, 10),
            $this->kw('what is tls', 1, 10),
        ];
        $intent = $analyzer->analyze($keywords, $this->components())['intent'];

        $this->assertSame(3, $intent['commercial_count']);
        $this->assertSame(3, $intent['informational_count']);
        $this->assertSame('commercial_informational', $intent['dominant']);
        $this->assertArrayHasKey('intent_scores', $intent);
        $this->assertGreaterThan(0, $intent['intent_scores']['commercial']);
        $this->assertGreaterThan(0, $intent['intent_scores']['informational']);
    }

    public function test_dominant_commercial_utility_when_best_and_generator_blend(): void
    {
        $analyzer = new KeywordStrategyAnalyzer;
        $intent = $analyzer->analyze([$this->kw('best password generator', 1, 50)], $this->components())['intent'];

        $this->assertSame('commercial_utility', $intent['dominant']);
        $this->assertGreaterThanOrEqual(1, $intent['blend_counts']['commercial_utility'] ?? 0);
    }

    public function test_dominant_single_winner_when_counts_unequal(): void
    {
        $analyzer = new KeywordStrategyAnalyzer;
        $keywords = [
            $this->kw('what is dns'),
            $this->kw('tips for sleep'),
            $this->kw('tutorial on css'),
            $this->kw('research on climate'),
            $this->kw('laptop review'),
        ];
        $intent = $analyzer->analyze($keywords, $this->components())['intent'];

        $this->assertSame('informational', $intent['dominant']);
        $this->assertGreaterThan($intent['commercial_count'], $intent['informational_count']);
    }
}
