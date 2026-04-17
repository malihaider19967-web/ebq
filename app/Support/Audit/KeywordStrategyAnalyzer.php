<?php

namespace App\Support\Audit;

class KeywordStrategyAnalyzer
{
    /** @var list<string> */
    private const COMMERCIAL_TRIGGERS = [
        'difference between', 'which is better', 'pros and cons', 'worth the money', 'scam or legit',
        'alternative to', 'best overall', 'top-rated', 'top rated', 'specifications', 'best alternative',
        'comparison', 'competitors', 'alternatives', 'benchmark', 'benefits', 'is it good', 'is it worth',
        'versus', 'reviews', 'review', 'rating', 'specs', 'vs',
    ];

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
     * @param array<int, array{query: string, clicks: int, impressions: int}> $targetKeywords
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
                $titleLower . ' ' . $h1Lower
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
     * @param array<int, array{query: string, clicks: int, impressions: int}> $keywords
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

        foreach ($keywords as $row) {
            $lower = mb_strtolower($row['query']);
            $q = $row['query'];

            if ($this->matchesAnyTrigger($lower, $commercialSorted)) {
                $commercial[] = $q;
            }
            if ($this->matchesAnyTrigger($lower, $transactionalSorted)) {
                $transactional[] = $q;
            }
            if ($this->matchesAnyTrigger($lower, $navigationalSorted)) {
                $navigational[] = $q;
            }
            if ($this->matchesAnyTrigger($lower, $localSorted)) {
                $local[] = $q;
            }
            if ($this->matchesAnyTrigger($lower, $supportSorted)) {
                $support[] = $q;
            }
            if ($this->matchesUtility($lower, $utilitySorted)) {
                $utility[] = $q;
            }
            if ($this->matchesInformational($lower, $informationalSorted)) {
                $informational[] = $q;
            }
        }

        $counts = [
            'informational' => count($informational),
            'utility' => count($utility),
            'commercial' => count($commercial),
            'transactional' => count($transactional),
            'navigational' => count($navigational),
            'local' => count($local),
            'support' => count($support),
        ];

        $max = max($counts);
        if ($max === 0) {
            $dominant = 'unclear';
        } else {
            $leaders = array_keys(array_filter($counts, fn (int $c) => $c === $max));
            $dominant = count($leaders) > 1 ? 'mixed' : $leaders[0];
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
            'dominant' => $dominant,
        ];
    }

    /**
     * @param  list<string>  $triggers  longest-first recommended
     */
    private function matchesAnyTrigger(string $lower, array $triggers): bool
    {
        foreach ($triggers as $t) {
            if ($t !== '' && str_contains($lower, $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $utilitySorted  longest-first
     */
    private function matchesUtility(string $lower, array $utilitySorted): bool
    {
        foreach ($utilitySorted as $t) {
            if ($t === '') {
                continue;
            }
            if ($t === 'free') {
                if (str_contains($lower, 'free trial') || str_contains($lower, 'freetrial')) {
                    continue;
                }
            }
            if (str_contains($lower, $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $informationalSorted  longest-first
     */
    private function matchesInformational(string $lower, array $informationalSorted): bool
    {
        foreach ($informationalSorted as $t) {
            if ($t === '') {
                continue;
            }
            if ($t === 'how to') {
                if (str_contains($lower, 'how to fix') || str_contains($lower, 'how to use')) {
                    continue;
                }
            }
            if (str_contains($lower, $t)) {
                return true;
            }
        }

        return false;
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
