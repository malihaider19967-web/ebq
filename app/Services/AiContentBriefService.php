<?php

namespace App\Services;

use App\Models\SearchConsoleData;
use App\Models\Website;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AI-generated content brief from a target keyword.
 *
 * Flow
 * ────
 *  1. Serper top-10 SERP for the keyword (paid; cached 7d).
 *  2. LLM extracts: subtopics-to-cover, recommended_word_count, suggested
 *     schema_type, must-have entities/people/places, suggested H2/H3
 *     outline, content angle (commercial / informational / comparison).
 *  3. We bolt on internal-link targets from the user's own site by
 *     pulling top GSC-clicked URLs that semantically match the keyword.
 *
 * The output is everything a writer needs to draft a competitive page:
 * structure, depth, schema, and where to internally link.
 *
 * Caching
 * ───────
 * Per (website × keyword × country) for 7 days. Re-clicks are free. New
 * content rarely shifts the brief in <1 week.
 *
 * MOAT
 * ────
 *  - Serper SERP scrape (paid)
 *  - Full prompt + few-shot examples (server-side)
 *  - Internal-link-target bolt-on requires GSC join (per-site data gravity)
 *  - All prompt-engineering tweaks happen on EBQ; plugin sees only output
 */
class AiContentBriefService
{
    private const CACHE_TTL_DAYS = 7;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly SerperSearchClient $serper,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   ok: bool,
     *   brief?: array<string, mixed>,
     *   cached?: bool,
     *   error?: string,
     * }
     */
    public function brief(Website $website, int $postId, array $input): array
    {
        if (! $this->llm->isAvailable()) {
            return ['ok' => false, 'error' => 'llm_not_configured'];
        }

        $keyword = trim((string) ($input['focus_keyword'] ?? ''));
        if ($keyword === '') {
            return ['ok' => false, 'error' => 'missing_focus_keyword'];
        }
        $country = is_string($input['country'] ?? null) && $input['country'] !== ''
            ? strtolower($input['country']) : 'us';
        $language = is_string($input['language'] ?? null) && $input['language'] !== ''
            ? strtolower($input['language']) : 'en';

        $cacheKey = sprintf(
            'ai_content_brief:%d:%s:%s',
            $website->id,
            hash('xxh3', mb_strtolower($keyword)),
            $country,
        );
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $cached['cached'] = true;
            return $cached;
        }

        $serp = $this->serper->search(
            $keyword,
            10,
            $country,
            $language,
            websiteId: $website->id,
            ownerUserId: $website->user_id,
        );
        if (! is_array($serp) || empty($serp['organic'])) {
            return ['ok' => false, 'error' => 'no_serp_data'];
        }

        $organic = array_slice(array_filter($serp['organic'], 'is_array'), 0, 10);
        $brief = $this->llm->completeJson($this->buildPrompt($keyword, $organic), [
            'temperature' => 0.4,
            'max_tokens' => 1500,
            'json_object' => true,
            'timeout' => 45,
        ]);

        if (! is_array($brief) || empty($brief['subtopics'])) {
            Log::warning('AiContentBriefService: malformed LLM response', [
                'website_id' => $website->id,
                'keyword' => $keyword,
            ]);
            return ['ok' => false, 'error' => 'llm_parse_failed'];
        }

        // Internal-link targets — top-clicked URLs on this site that
        // semantically relate to the keyword. Lightweight bolt-on; reuses
        // the existing resolver helper to avoid duplicating GSC logic.
        $internalTargets = $this->internalLinkTargets($website, $keyword);

        $result = [
            'ok' => true,
            'brief' => [
                'keyword' => $keyword,
                'country' => $country,
                'angle' => (string) ($brief['angle'] ?? 'informational'),
                'recommended_word_count' => max(0, (int) ($brief['recommended_word_count'] ?? 0)),
                'suggested_schema_type' => (string) ($brief['suggested_schema_type'] ?? 'Article'),
                'subtopics' => $this->normalizeStringList($brief['subtopics'] ?? [], 20),
                'must_have_entities' => $this->normalizeStringList($brief['must_have_entities'] ?? [], 12),
                'suggested_outline' => $this->normalizeOutline($brief['suggested_outline'] ?? []),
                'people_also_ask' => $this->normalizeStringList($brief['people_also_ask'] ?? [], 10),
                'internal_link_targets' => $internalTargets,
                'serp_titles' => array_values(array_filter(array_map(
                    fn ($r) => is_array($r) && isset($r['title']) ? (string) $r['title'] : null,
                    $organic,
                ))),
            ],
            'cached' => false,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, Carbon::now()->addDays(self::CACHE_TTL_DAYS));
        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $organic
     * @return list<array{role: string, content: string}>
     */
    private function buildPrompt(string $keyword, array $organic): array
    {
        $serpBlock = '';
        foreach ($organic as $i => $row) {
            $title = (string) ($row['title'] ?? '');
            $snippet = (string) ($row['snippet'] ?? '');
            $link = (string) ($row['link'] ?? '');
            $serpBlock .= sprintf(
                "%d. %s\n   %s\n   %s\n",
                $i + 1,
                mb_substr($title, 0, 120),
                mb_substr($link, 0, 120),
                mb_substr($snippet, 0, 240),
            );
        }

        $system = <<<'SYS'
You are a senior SEO content strategist. Given a target keyword and the
top-ranking pages, you produce a content brief sharp enough that a writer
can draft a winning page in one sitting.

Constraints:
- subtopics: 8–14 specific topics the page must cover, derived from what
  the top SERP pages share. Not generic ("introduction", "conclusion").
- recommended_word_count: integer; based on the median word depth implied
  by the SERP titles + snippets. 600 floor, 4500 ceiling.
- suggested_schema_type: one of Article, HowTo, FAQ, Review, Product,
  Recipe, Event, Course, LocalBusiness — pick the SINGLE best fit.
- must_have_entities: people, brands, products, places, frameworks the
  competitors mention in their titles/snippets.
- suggested_outline: 6–10 H2-level section titles in narrative order.
- people_also_ask: 5–10 questions a reader is likely to want answered.
- angle: commercial | informational | comparison | guide | listicle.

Return STRICTLY valid JSON. No prose, no markdown.
SYS;

        $user = <<<USER
Target keyword: "{$keyword}"

Top 10 SERP results:
{$serpBlock}

Return JSON exactly in this shape:
{
  "angle": "...",
  "recommended_word_count": 1800,
  "suggested_schema_type": "Article",
  "subtopics": ["...", "..."],
  "must_have_entities": ["...", "..."],
  "suggested_outline": ["H2 title 1", "H2 title 2", "..."],
  "people_also_ask": ["...", "..."]
}
USER;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * Top-clicked URLs on this site whose GSC queries semantically match
     * the brief's keyword. Built directly from `search_console_data` so the
     * brief works even before any post is published — we only need the
     * site's existing GSC footprint, not a canonical URL.
     *
     * Returns up to 6 candidates with the matched query as the anchor hint.
     *
     * @return list<array{url: string, anchor_hint: string, clicks_30d: int}>
     */
    private function internalLinkTargets(Website $website, string $keyword): array
    {
        $tokens = $this->significantTokens($keyword);
        if ($tokens === []) {
            return [];
        }

        try {
            $tz = config('app.timezone');
            $end = Carbon::yesterday($tz)->endOfDay();
            $start = $end->copy()->subDays(89)->startOfDay();

            $rows = SearchConsoleData::query()
                ->where('website_id', $website->id)
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->where('page', '!=', '')
                ->where('query', '!=', '')
                ->where(function ($w) use ($tokens) {
                    foreach ($tokens as $tok) {
                        $w->orWhere('query', 'LIKE', '%' . $tok . '%');
                    }
                })
                ->selectRaw('page, query, SUM(clicks) AS clicks')
                ->groupBy('page', 'query')
                ->orderByDesc('clicks')
                ->limit(40)
                ->get();

            $byPage = [];
            foreach ($rows as $r) {
                $page = (string) $r->page;
                if (! isset($byPage[$page])) {
                    $byPage[$page] = [
                        'url' => $page,
                        'anchor_hint' => (string) $r->query,
                        'clicks_30d' => (int) $r->clicks,
                    ];
                } else {
                    $byPage[$page]['clicks_30d'] += (int) $r->clicks;
                }
            }
            uasort($byPage, fn ($a, $b) => $b['clicks_30d'] <=> $a['clicks_30d']);
            return array_values(array_slice($byPage, 0, 6));
        } catch (\Throwable $e) {
            Log::warning('AiContentBriefService: internal link candidate lookup failed', [
                'website_id' => $website->id,
                'keyword' => $keyword,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function significantTokens(string $text): array
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') return [];
        $parts = preg_split('/[^a-z0-9]+/u', $text) ?: [];
        $stop = ['the', 'and', 'for', 'with', 'a', 'an', 'of', 'to', 'in', 'on', 'is', 'are', 'or'];
        $out = [];
        foreach ($parts as $p) {
            if (mb_strlen($p) >= 3 && ! in_array($p, $stop, true)) {
                $out[$p] = true;
            }
            if (count($out) >= 5) break;
        }
        return array_keys($out);
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function normalizeStringList($raw, int $cap): array
    {
        if (! is_array($raw)) return [];
        $out = [];
        foreach ($raw as $v) {
            if (! is_string($v)) continue;
            $s = trim($v);
            if ($s === '') continue;
            $out[] = mb_substr($s, 0, 200);
            if (count($out) >= $cap) break;
        }
        return $out;
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function normalizeOutline($raw): array
    {
        return $this->normalizeStringList($raw, 12);
    }
}
