<?php

namespace App\Support\Audit;

class KeywordStrategyAnalyzer
{
    private const INFO_TRIGGERS = ['how to', 'why', 'what is', 'guide', 'tips', 'tutorial', 'ideas', 'best', 'examples'];
    private const UTILITY_TRIGGERS = ['generator', 'tool', 'maker', 'online', 'download', 'creator', 'converter', 'calculator', 'editor', 'free'];

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

    private function intentAlignment(array $keywords): array
    {
        $informational = [];
        $utility = [];

        foreach ($keywords as $row) {
            $lower = mb_strtolower($row['query']);
            $isInfo = false;
            $isUtility = false;
            foreach (self::INFO_TRIGGERS as $t) {
                if (str_contains($lower, $t)) {
                    $isInfo = true;
                    break;
                }
            }
            foreach (self::UTILITY_TRIGGERS as $t) {
                if (str_contains($lower, $t)) {
                    $isUtility = true;
                    break;
                }
            }
            if ($isInfo) {
                $informational[] = $row['query'];
            }
            if ($isUtility) {
                $utility[] = $row['query'];
            }
        }

        $infoCount = count($informational);
        $utilityCount = count($utility);
        $dominant = match (true) {
            $utilityCount === 0 && $infoCount === 0 => 'unclear',
            $utilityCount > $infoCount => 'utility',
            $infoCount > $utilityCount => 'informational',
            default => 'mixed',
        };

        return [
            'informational_count' => $infoCount,
            'utility_count' => $utilityCount,
            'informational_examples' => array_slice($informational, 0, 5),
            'utility_examples' => array_slice($utility, 0, 5),
            'dominant' => $dominant,
        ];
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
