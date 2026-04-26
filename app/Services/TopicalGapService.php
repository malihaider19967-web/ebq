<?php

namespace App\Services;

use App\Models\Website;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Facades\Cache;

/**
 * Topical-coverage gap analysis. Uses Serper to grab the top-5 results for
 * a focus keyword + Mistral to extract the subtopics those competitors
 * cover, then compares against the user's content and returns the missing
 * subtopics with one-line rationales.
 *
 * Heavy operation (Serper credits + LLM tokens) so every call is cached
 * for 7 days keyed on (website × keyword × country × content-hash). The
 * content-hash makes the cache auto-invalidate when the user edits.
 *
 * Returns:
 *   [
 *     'available' => bool,
 *     'reason' => string|null,
 *     'your_subtopics' => list<string>,
 *     'competitor_subtopics' => list<string>,
 *     'missing' => list<{topic, rationale, sources: list<{url,title}>}>,
 *     'covered' => list<string>,
 *   ]
 */
class TopicalGapService
{
    /** Cache TTL — Serper + LLM are expensive; 7 days is a reasonable refresh window. */
    private const CACHE_TTL = 60 * 60 * 24 * 7;

    public function __construct(
        private readonly SerperSearchClient $serper,
        private readonly LlmClient $llm,
    ) {}

    public function analyze(
        Website $website,
        string $focusKeyword,
        string $content,
        ?string $country = null,
        ?string $language = null,
    ): array {
        $kw = trim($focusKeyword);
        if ($kw === '') {
            return $this->unavailable('missing_focus_keyword');
        }
        if (! $this->llm->isAvailable()) {
            return $this->unavailable('llm_not_configured');
        }

        $contentText = trim(strip_tags($content));
        if (mb_strlen($contentText) < 200) {
            return $this->unavailable('content_too_short');
        }

        $country = $country ?: 'us';
        $language = $language ?: 'en';

        $cacheKey = sprintf(
            'ebq_topical_gaps_v1_%d_%s_%s_%s_%s',
            $website->id,
            mb_strtolower($kw),
            $country,
            $language,
            substr(hash('sha256', $contentText), 0, 12),
        );

        // We DELIBERATELY don't use Cache::remember here: that would cache
        // the failure tuple too (`available=false, reason=llm_parse_failed`,
        // etc.), pinning a transient error for 7 days even after the
        // upstream issue clears. Instead: read the cache; on miss compute;
        // only persist on success. Re-clicks during the failure window now
        // actually retry instead of returning the bad cached result.
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->compute($website, $kw, $contentText, $country, $language);
        if (($result['available'] ?? false) === true) {
            Cache::put($cacheKey, $result, self::CACHE_TTL);
        }
        return $result;
    }

    private function compute(Website $website, string $kw, string $contentText, string $country, string $language): array
    {
        // ── Step 1: top-5 SERP results.
        $serp = $this->serper->search($kw, 5, $country, $language, $website->id, $website->user_id);
        $organic = is_array($serp['organic'] ?? null) ? $serp['organic'] : [];
        $organic = array_slice($organic, 0, 5);
        if (empty($organic)) {
            return $this->unavailable('no_serp_data');
        }

        // ── Step 2: ask the LLM to extract subtopics from competitors AND
        //    the user's content in one call. Strict JSON output so we can
        //    parse without hand-rolling tolerant parsing.
        $competitorBlock = $this->buildCompetitorBlock($organic);
        $userExcerpt = mb_substr($contentText, 0, 6000); // cap input tokens

        $messages = [
            [
                'role' => 'system',
                'content' => "You are an SEO topical-coverage analyst. Given the top SERP results for a target keyword and the user's draft content, extract the distinct subtopics (concrete content sections, not generic descriptors) each side covers. Subtopics should be 1-5 word noun phrases (e.g. 'Cushioning', 'Heel-to-toe drop', 'Brand comparison'). Output strict JSON.",
            ],
            [
                'role' => 'user',
                'content' => sprintf(
                    "Target keyword: %s\nLanguage: %s\n\nCompetitor pages (titles + snippets):\n%s\n\nUser's draft content (first 6000 chars):\n%s\n\nReturn JSON with this exact shape:\n{\n  \"your_subtopics\": [\"...\"],\n  \"competitor_subtopics\": [\"...\"],\n  \"missing\": [{\"topic\":\"...\", \"rationale\":\"why this matters in 1 sentence\", \"sources\":[{\"url\":\"...\",\"title\":\"...\"}]}],\n  \"covered\": [\"...\"]\n}\n\nRules:\n- Up to 12 subtopics per side.\n- 'missing' = subtopics covered by 2+ competitors but absent from user's draft.\n- 'covered' = subtopics present in BOTH the user's draft and at least one competitor.\n- 'sources' for missing subtopics = up to 2 competitor pages that cover that subtopic.\n- Subtopics are CONCRETE (e.g. 'Heel-toe drop'), not generic ('Tips', 'Conclusion').",
                    $kw, $language, $competitorBlock, $userExcerpt,
                ),
            ],
        ];

        // Larger budgets than the original — competitor-block + 6000-char
        // user excerpt routinely produces 1500-token JSON, and Mistral in
        // strict-JSON mode is noticeably slower than free-text mode. Old
        // 18s / 1200tok combo was the actual cause of "AI returned
        // malformed output" — responses were getting truncated mid-JSON,
        // which then failed strict decode with no recovery path.
        $decoded = $this->llm->completeJson($messages, [
            'temperature' => 0.15,
            'max_tokens' => 2200,
            'timeout' => 28,
        ]);

        if (! is_array($decoded)) {
            return $this->unavailable('llm_parse_failed');
        }

        $sources = [];
        foreach ($organic as $r) {
            $sources[] = [
                'url' => (string) ($r['link'] ?? ''),
                'title' => (string) ($r['title'] ?? ''),
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'your_subtopics'       => $this->cleanStringList($decoded['your_subtopics']        ?? []),
            'competitor_subtopics' => $this->cleanStringList($decoded['competitor_subtopics'] ?? []),
            'missing'              => $this->cleanMissingList($decoded['missing']             ?? []),
            'covered'              => $this->cleanStringList($decoded['covered']              ?? []),
            'serp_sources'         => $sources,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $organic
     */
    private function buildCompetitorBlock(array $organic): string
    {
        $lines = [];
        foreach ($organic as $i => $r) {
            $title = (string) ($r['title'] ?? '');
            $snippet = (string) ($r['snippet'] ?? '');
            $url = (string) ($r['link'] ?? '');
            $lines[] = sprintf("[%d] %s\n%s\nURL: %s", $i + 1, $title, mb_substr($snippet, 0, 280), $url);
        }
        return implode("\n\n", $lines);
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function cleanStringList($value): array
    {
        if (! is_array($value)) return [];
        $out = [];
        foreach ($value as $item) {
            if (! is_string($item)) continue;
            $clean = trim($item);
            if ($clean === '') continue;
            $clean = mb_substr($clean, 0, 80);
            $out[] = $clean;
            if (count($out) >= 15) break;
        }
        return array_values(array_unique($out));
    }

    /**
     * @param  mixed  $value
     * @return list<array{topic:string, rationale:string, sources:list<array{url:string,title:string}>}>
     */
    private function cleanMissingList($value): array
    {
        if (! is_array($value)) return [];
        $out = [];
        foreach ($value as $item) {
            if (! is_array($item)) continue;
            $topic = trim((string) ($item['topic'] ?? ''));
            if ($topic === '') continue;
            $rationale = trim((string) ($item['rationale'] ?? ''));
            $rawSources = is_array($item['sources'] ?? null) ? $item['sources'] : [];
            $sources = [];
            foreach ($rawSources as $s) {
                if (! is_array($s)) continue;
                $url = trim((string) ($s['url'] ?? ''));
                $title = trim((string) ($s['title'] ?? ''));
                if ($url === '') continue;
                $sources[] = ['url' => $url, 'title' => $title !== '' ? $title : $url];
                if (count($sources) >= 3) break;
            }
            $out[] = [
                'topic' => mb_substr($topic, 0, 80),
                'rationale' => mb_substr($rationale, 0, 240),
                'sources' => $sources,
            ];
            if (count($out) >= 12) break;
        }
        return $out;
    }

    private function unavailable(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'your_subtopics' => [],
            'competitor_subtopics' => [],
            'missing' => [],
            'covered' => [],
            'serp_sources' => [],
        ];
    }
}
