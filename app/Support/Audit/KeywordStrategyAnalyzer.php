<?php

namespace App\Support\Audit;

class KeywordStrategyAnalyzer
{
    /** @var list<string> */
    private const COMMERCIAL_TRIGGERS = [
        'difference between', 'which is better', 'pros and cons', 'worth the money', 'scam or legit',
        'alternative to', 'best overall', 'top-rated', 'top rated', 'specifications', 'best alternative',
        'comparison', 'competitors', 'alternatives', 'benchmark', 'benefits', 'is it good', 'is it worth',
        'versus', 'reviews', 'review', 'rating', 'specs', 'best', 'vs',
    ];

    private const COMPOUND_RUNNER_RATIO = 0.45;

    private const COMPOUND_RUNNER_MIN_SCORE = 2.0;

    private const MAX_HITS_PER_BUCKET_PER_QUERY = 8;

    /** @var list<string> */
    private const TRANSACTIONAL_TRIGGERS = [
        'promo code', 'promocode', 'premium version', 'upgrade to pro', 'get it now', 'free trial', 'freetrial',
        'for sale', 'subscription', 'affordable', 'clearance', 'checkout',
        'purchase', 'discount', 'voucher', 'coupon', 'reserve', 'pricing', 'enroll', 'cheap', 'deal',
        'order', 'shop', 'hire', 'book', 'cost', 'price', 'buy',
    ];

    /** @var list<string> */
    private const NAVIGATIONAL_TRIGGERS = [
        'customer service', 'support phone', 'official site', 'my account', 'home page', 'homepage', 'dashboard', 'sign-in',
        'sign in', 'signin', 'log in', 'login', 'portal', 'account', 'contact', 'careers', 'career',
        'jobs', 'press',
    ];

    /** @var list<string> */
    private const LOCAL_TRIGGERS = [
        'open today', 'open now', 'near me', 'nearby', 'around me', 'closest', 'pick up', 'pickup',
        'delivery to', 'in my area', 'zip code', 'zipcode', 'directions', 'address', 'hours',
        'local',
    ];

    /** @var list<string> */
    private const SUPPORT_TRIGGERS = [
        'not working', "doesn't work", 'doesnt work', 'reset password', 'forgotten password',
        'forgot password', 'troubleshoot', 'how to fix', 'how to use', 'uninstalled', 'uninstall',
        'failed', 'broken', 'stuck', 'error', 'manual', 'setup', 'install', 'cancel', 'refund', 'bug',
        'fix', 'slow',
    ];

    /** @var list<string> */
    private const UTILITY_TRIGGERS = [
        'free tool', 'online tool', 'downloader', 'generator', 'converter', 'calculator', 'checker',
        'tester', 'builder', 'scanner', 'viewer', 'editor', 'creator', 'maker', 'online', 'download',
        'tool', 'free',
    ];

    /** @var list<string> */
    private const INFORMATIONAL_TRIGGERS = [
        'whitepaper', 'case study', 'statistics', 'examples of', 'meaning of', 'history of',
        'when was', 'why does', 'what is', 'research', 'tutorial', 'stats', 'tips', 'ideas', 'how to',
    ];

    /**
     * @param  array<int, array{query: string, clicks: int, impressions: int}>  $targetKeywords
     */
    public function analyze(array $targetKeywords, array $components): array
    {
        if (empty($targetKeywords)) {
            return [
                'available' => false,
                'reason' => 'No Search Console queries available for this page yet. Once Google starts surfacing impressions, target-keyword analysis will appear here.',
            ];
        }

        $bodyLower = mb_strtolower($components['body_text'] ?? '');
        $titleLower = mb_strtolower($components['title'] ?? '');
        $descLower = mb_strtolower($components['meta_description'] ?? '');
        $h1Lower = mb_strtolower($components['h1_text'] ?? '');
        $allHeadingsLower = mb_strtolower($components['all_headings_text'] ?? '');

        $primary = $targetKeywords[0];

        return [
            'available' => true,
            'target_keywords' => $targetKeywords,
            'primary' => $primary,
            'power_placement' => $this->powerPlacement($primary['query'], $titleLower, $descLower, $h1Lower),
            'coverage' => $this->coverage($targetKeywords, $bodyLower),
            'intent' => $this->intentAlignment($targetKeywords),
            'accidental' => $this->accidentalAuthority(
                $components['keyword_density'] ?? [],
                $targetKeywords,
                $titleLower.' '.$h1Lower
            ),
        ];
    }

    private function powerPlacement(string $keyword, string $title, string $desc, string $h1): array
    {
        $kw = mb_strtolower(trim($keyword));

        return [
            'keyword' => $keyword,
            'in_title' => $kw !== '' && str_contains($title, $kw),
            'in_meta_description' => $kw !== '' && str_contains($desc, $kw),
            'in_h1' => $kw !== '' && str_contains($h1, $kw),
        ];
    }

    private function coverage(array $keywords, string $body): array
    {
        $found = [];
        $missing = [];
        foreach ($keywords as $row) {
            $kw = mb_strtolower(trim($row['query']));
            if ($kw === '') {
                continue;
            }
            if (str_contains($body, $kw)) {
                $found[] = $row;
            } else {
                $missing[] = $row;
            }
        }
        $total = count($keywords);
        $score = $total > 0 ? round((count($found) / $total) * 100, 1) : 0.0;

        $verdict = match (true) {
            $score >= 80 => 'high_authority',
            $score < 50 => 'expansion_needed',
            default => 'partial',
        };

        return [
            'total' => $total,
            'found_count' => count($found),
            'missing_count' => count($missing),
            'missing' => array_slice($missing, 0, 30),
            'score' => $score,
            'verdict' => $verdict,
        ];
    }

    /**
     * @param  array<int, array{query: string, clicks: int, impressions: int}>  $keywords
     */
    private function intentAlignment(array $keywords): array
    {
        $commercialSorted = self::sortTriggersDesc(self::COMMERCIAL_TRIGGERS);
        $transactionalSorted = self::sortTriggersDesc(self::TRANSACTIONAL_TRIGGERS);
        $navigationalSorted = self::sortTriggersDesc(self::NAVIGATIONAL_TRIGGERS);
        $localSorted = self::sortTriggersDesc(self::LOCAL_TRIGGERS);
        $supportSorted = self::sortTriggersDesc(self::SUPPORT_TRIGGERS);
        $utilitySorted = self::sortTriggersDesc(self::UTILITY_TRIGGERS);
        $informationalSorted = self::sortTriggersDesc(self::INFORMATIONAL_TRIGGERS);

        $commercial = [];
        $transactional = [];
        $navigational = [];
        $local = [];
        $support = [];
        $utility = [];
        $informational = [];

        $scores = [
            'informational' => 0.0,
            'utility' => 0.0,
            'commercial' => 0.0,
            'transactional' => 0.0,
            'navigational' => 0.0,
            'local' => 0.0,
            'support' => 0.0,
        ];

        /** @var array<string, int> */
        $blendCounts = [];

        foreach ($keywords as $row) {
            $lower = mb_strtolower($row['query']);
            $q = $row['query'];
            $w = log(1 + max(1, (int) ($row['impressions'] ?? 0)));

            $hitsCommercial = $this->countStandardBucketHits($lower, $commercialSorted);
            $hitsTransactional = $this->countStandardBucketHits($lower, $transactionalSorted);
            $hitsNavigational = $this->countStandardBucketHits($lower, $navigationalSorted);
            $hitsLocal = $this->countStandardBucketHits($lower, $localSorted);
            $hitsSupport = $this->countStandardBucketHits($lower, $supportSorted);
            $hitsUtility = $this->countUtilityHits($lower, $utilitySorted);
            $hitsInformational = $this->countInformationalHits($lower, $informationalSorted);

            $scores['commercial'] += $hitsCommercial * $w;
            $scores['transactional'] += $hitsTransactional * $w;
            $scores['navigational'] += $hitsNavigational * $w;
            $scores['local'] += $hitsLocal * $w;
            $scores['support'] += $hitsSupport * $w;
            $scores['utility'] += $hitsUtility * $w;
            $scores['informational'] += $hitsInformational * $w;

            $hitMap = [
                'commercial' => $hitsCommercial,
                'transactional' => $hitsTransactional,
                'navigational' => $hitsNavigational,
                'local' => $hitsLocal,
                'support' => $hitsSupport,
                'utility' => $hitsUtility,
                'informational' => $hitsInformational,
            ];

            if ($hitsCommercial > 0) {
                $commercial[] = $q;
            }
            if ($hitsTransactional > 0) {
                $transactional[] = $q;
            }
            if ($hitsNavigational > 0) {
                $navigational[] = $q;
            }
            if ($hitsLocal > 0) {
                $local[] = $q;
            }
            if ($hitsSupport > 0) {
                $support[] = $q;
            }
            if ($hitsUtility > 0) {
                $utility[] = $q;
            }
            if ($hitsInformational > 0) {
                $informational[] = $q;
            }

            $active = array_keys(array_filter($hitMap, fn (int $h) => $h > 0));
            sort($active);
            $n = count($active);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $pair = $active[$i].'_'.$active[$j];
                    $blendCounts[$pair] = ($blendCounts[$pair] ?? 0) + 1;
                }
            }
        }

        $counts = [
            'informational' => count(array_unique($informational)),
            'utility' => count(array_unique($utility)),
            'commercial' => count(array_unique($commercial)),
            'transactional' => count(array_unique($transactional)),
            'navigational' => count(array_unique($navigational)),
            'local' => count(array_unique($local)),
            'support' => count(array_unique($support)),
        ];

        $informational = array_values(array_unique($informational));
        $utility = array_values(array_unique($utility));
        $commercial = array_values(array_unique($commercial));
        $transactional = array_values(array_unique($transactional));
        $navigational = array_values(array_unique($navigational));
        $local = array_values(array_unique($local));
        $support = array_values(array_unique($support));

        $dominant = $this->resolveDominantFromScores($scores);

        $intentScoresRounded = [];
        foreach ($scores as $k => $v) {
            $intentScoresRounded[$k] = round($v, 1);
        }

        return [
            'informational_count' => $counts['informational'],
            'utility_count' => $counts['utility'],
            'commercial_count' => $counts['commercial'],
            'transactional_count' => $counts['transactional'],
            'navigational_count' => $counts['navigational'],
            'local_count' => $counts['local'],
            'support_count' => $counts['support'],
            'informational_examples' => array_slice($informational, 0, 5),
            'utility_examples' => array_slice($utility, 0, 5),
            'commercial_examples' => array_slice($commercial, 0, 5),
            'transactional_examples' => array_slice($transactional, 0, 5),
            'navigational_examples' => array_slice($navigational, 0, 5),
            'local_examples' => array_slice($local, 0, 5),
            'support_examples' => array_slice($support, 0, 5),
            'intent_scores' => $intentScoresRounded,
            'blend_counts' => $blendCounts,
            'dominant' => $dominant,
        ];
    }

    /**
     * @param  list<string>  $triggersSorted  longest-first
     */
    private function countStandardBucketHits(string $lower, array $triggersSorted): int
    {
        $n = 0;
        foreach ($triggersSorted as $t) {
            if ($t !== '' && str_contains($lower, $t)) {
                $n++;
            }
        }

        return min($n, self::MAX_HITS_PER_BUCKET_PER_QUERY);
    }

    /**
     * @param  list<string>  $utilitySorted  longest-first
     */
    private function countUtilityHits(string $lower, array $utilitySorted): int
    {
        $n = 0;
        foreach ($utilitySorted as $t) {
            if ($t === '') {
                continue;
            }
            if ($t === 'free' && (str_contains($lower, 'free trial') || str_contains($lower, 'freetrial'))) {
                continue;
            }
            if (str_contains($lower, $t)) {
                $n++;
            }
        }

        return min($n, self::MAX_HITS_PER_BUCKET_PER_QUERY);
    }

    /**
     * @param  list<string>  $informationalSorted  longest-first
     */
    private function countInformationalHits(string $lower, array $informationalSorted): int
    {
        $n = 0;
        foreach ($informationalSorted as $t) {
            if ($t === '') {
                continue;
            }
            if ($t === 'how to' && (str_contains($lower, 'how to fix') || str_contains($lower, 'how to use'))) {
                continue;
            }
            if (str_contains($lower, $t)) {
                $n++;
            }
        }

        return min($n, self::MAX_HITS_PER_BUCKET_PER_QUERY);
    }

    /**
     * @param  array<string, float>  $scores
     */
    private function resolveDominantFromScores(array $scores): string
    {
        $positive = array_filter($scores, fn (float $v) => $v > 1e-9);
        if ($positive === []) {
            return 'unclear';
        }

        arsort($positive);
        $values = array_values($positive);
        $maxV = $values[0];
        $atMax = array_keys(array_filter($positive, fn (float $v) => abs($v - $maxV) < 1e-6));

        if (count($atMax) >= 3) {
            return 'mixed';
        }

        if (count($atMax) === 2) {
            sort($atMax);

            return implode('_', $atMax);
        }

        $keys = array_keys($positive);
        $top = $keys[0];
        $topV = $positive[$top];
        $secondV = $values[1] ?? 0.0;
        $secondKey = $keys[1] ?? null;

        if ($secondKey !== null
            && $secondV >= self::COMPOUND_RUNNER_RATIO * $topV
            && $secondV >= self::COMPOUND_RUNNER_MIN_SCORE) {
            $pair = [$top, $secondKey];
            sort($pair);

            return implode('_', $pair);
        }

        return $top;
    }

    /**
     * @param  list<string>  $phrases
     * @return list<string>
     */
    private static function sortTriggersDesc(array $phrases): array
    {
        $phrases = array_values(array_unique($phrases));
        usort($phrases, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return $phrases;
    }

    private function accidentalAuthority(array $density, array $targets, string $titleAndH1): array
    {
        $targetTerms = array_map(fn ($r) => mb_strtolower($r['query']), $targets);
        $results = [];

        foreach ($density as $entry) {
            $term = mb_strtolower($entry['term'] ?? '');
            $d = (float) ($entry['count'] ? ($entry['density'] ?? 0) : 0);
            if ($term === '' || $d <= 2.0) {
                continue;
            }

            $inTargets = false;
            foreach ($targetTerms as $t) {
                if ($t !== '' && (str_contains($t, $term) || str_contains($term, $t))) {
                    $inTargets = true;
                    break;
                }
            }
            if ($inTargets) {
                continue;
            }
            if (str_contains($titleAndH1, $term)) {
                continue;
            }

            $results[] = $entry;
        }

        return array_slice($results, 0, 5);
    }
}
