<?php

namespace App\Services;

use App\Models\SearchConsoleData;
use App\Models\Website;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AI writer — generates section-level proposals for a post.
 *
 * Inputs (any subset is fine; the service degrades gracefully when one is
 * missing):
 *   - existing post HTML / excerpt
 *   - the post's content brief (subtopics, must-have entities, outline)
 *   - the topical-coverage gaps (subtopics competitors cover that this
 *     post doesn't)
 *
 * Output: a list of section proposals, each tagged as `add`, `edit`, or
 * `replace`, with current and proposed HTML so the editor can render a
 * diff and the user can approve / reject per section. The frontend then
 * applies the approved sections by writing the merged HTML back via WP
 * core's REST POST /wp/v2/posts/{id} endpoint.
 *
 * Cached 24h by (website × postId × focusKeyword × content-hash) so
 * re-clicks during the same drafting session are free.
 */
class AiWriterService
{
    private const CACHE_TTL_SEC = 60 * 60 * 24;

    /**
     * 20 sections × ~400 words avg ≈ 10–12k output tokens; the 16k
     * `max_tokens` budget below leaves headroom for JSON delimiters and
     * metadata. The model is told to aim for substantive sections but
     * skip ones that would just pad — quality over hitting the cap.
     */
    private const MAX_SECTIONS = 20;

    /** Per-section HTML cap (chars). 6000 ≈ 900 words of prose. */
    private const SECTION_HTML_CAP = 6000;

    public function __construct(
        private readonly LlmClient $llm,
    ) {}

    /**
     * @param  array{
     *   focus_keyword: string,
     *   current_html?: string|null,
     *   brief?: array<string, mixed>|null,
     *   gaps?: array<string, mixed>|null,
     * }  $input
     * @return array<string, mixed>
     */
    public function draft(Website $website, int $postId, array $input): array
    {
        if (! $this->llm->isAvailable()) {
            return ['ok' => false, 'error' => 'llm_not_configured'];
        }

        $keyword = trim((string) ($input['focus_keyword'] ?? ''));
        if ($keyword === '' || mb_strlen($keyword) < 2) {
            return ['ok' => false, 'error' => 'missing_focus_keyword'];
        }

        $currentHtml = (string) ($input['current_html'] ?? '');
        $currentText = trim(strip_tags($currentHtml));
        $brief = is_array($input['brief'] ?? null) ? $input['brief'] : null;
        $gaps = is_array($input['gaps'] ?? null) ? $input['gaps'] : null;
        $excludeUrl = trim((string) ($input['exclude_url'] ?? ''));
        $selected = is_array($input['selected'] ?? null) ? $input['selected'] : null;

        // When the user has curated a selection in the plan step, filter
        // the brief / gaps lists down to those items so the prompt isn't
        // told to cover topics the user explicitly skipped.
        if ($selected !== null) {
            [$brief, $gaps] = $this->applySelection($brief, $gaps, $selected);
        }
        $wpPages = is_array($input['wp_pages'] ?? null) ? $input['wp_pages'] : [];

        // Topic-aware internal-link candidate pool. GSC click data is the
        // moat — pages with proven user demand always beat "exists but
        // unranked" candidates. The wp_pages fallback lets newly-published
        // content surface before it accumulates GSC impressions.
        $smartLinks = $this->resolveSmartInternalLinks($website, $excludeUrl, $keyword, $brief, $gaps, $wpPages);

        $hasBrief = $brief !== null && ! empty($brief['subtopics'] ?? []);
        $hasGaps = $gaps !== null && ! empty($gaps['missing'] ?? []);
        $hasContent = mb_strlen($currentText) >= 100;
        $availableInternalLinks = count($smartLinks);
        $availablePaaCount = is_array($brief['people_also_ask'] ?? null)
            ? count(array_filter($brief['people_also_ask'], 'is_string'))
            : 0;
        $availableGapsCount = is_array($gaps['missing'] ?? null)
            ? count(array_filter($gaps['missing'], 'is_array'))
            : 0;
        // Look at the raw HTML (block markers and all) for an <h1> — the
        // strip_tags'd $currentText loses tag info. Editor themes render the
        // post title as H1, so most published posts won't have one inside the
        // content; the writer should add one when missing.
        $hasH1 = (bool) preg_match('/<h1\b/i', (string) ($input['current_html'] ?? ''));

        // No hard guard on inputs — the writer can produce a useful first
        // draft from just the focus keyword (and any user-curated
        // selection from the plan step). Brief / gaps / existing content
        // make output richer and more grounded, but they are NOT required.
        // The prompt's all-empty case (see below) handles graceful
        // degradation.

        // Cache version — bump when the prompt or output shape changes so
        // existing cached results don't pin users to a stale generation.
        // v11: strict mode now hard-forbids kind="replace" and removes the
        //      input-scarcity fallback paragraph that was tempting the
        //      model to produce one consolidated "replace" section even
        //      with a curated selection.
        $selectionHash = $selected !== null
            ? substr(hash('sha256', json_encode($selected, JSON_UNESCAPED_UNICODE) ?: ''), 0, 12)
            : '0';
        $cacheKey = sprintf(
            'ai_writer_v11:%d:%d:%s:%s:%s:%s:%d:%d:%s',
            $website->id,
            $postId,
            hash('xxh3', mb_strtolower($keyword)),
            $hasBrief ? '1' : '0',
            $hasGaps ? '1' : '0',
            substr(hash('sha256', $currentText), 0, 12),
            count($smartLinks),
            count($wpPages),
            $selectionHash,
        );
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['ok'] ?? false) === true) {
            $cached['cached'] = true;

            return $cached;
        }

        $messages = $this->buildPrompt($keyword, $currentText, $brief, $gaps, $hasH1, $smartLinks, $selected !== null);
        // 16k output tokens supports the 20-section cap with room for JSON
        // overhead. Mistral Small's 32k context window comfortably fits
        // input + this output. 240s timeout matches the worst-case wall
        // time for large JSON-mode generations.
        $response = $this->llm->completeJson($messages, [
            'temperature' => 0.5,
            'max_tokens' => 16000,
            'json_object' => true,
            'timeout' => 240,
        ]);

        if (! is_array($response) || ! isset($response['sections']) || ! is_array($response['sections'])) {
            Log::warning('AiWriterService: malformed LLM response', [
                'website_id' => $website->id,
                'post_id' => $postId,
                'keyword' => $keyword,
                'response_type' => gettype($response),
                'response_keys' => is_array($response) ? array_keys($response) : null,
            ]);

            return [
                'ok' => false,
                'error' => 'llm_parse_failed',
                'message' => is_array($response)
                    ? 'The model returned JSON but without a "sections" array — try regenerating; if it persists, the focus keyword may be too vague.'
                    : 'The model response could not be parsed as JSON. Re-try once; if it persists, try a more specific focus keyword or shorter source content.',
            ];
        }

        $sections = $this->normalizeSections($response['sections']);

        // Belt-and-suspenders for strict mode: drop any "replace" sections
        // the model snuck in despite the prompt. They collapse the entire
        // generation into one block, which is exactly the bug strict mode
        // exists to prevent.
        if ($selected !== null) {
            $beforeCount = count($sections);
            $sections = array_values(array_filter(
                $sections,
                static fn (array $s) => ($s['kind'] ?? '') !== 'replace',
            ));
            $dropped = $beforeCount - count($sections);
            if ($dropped > 0) {
                Log::warning('AiWriterService: stripped replace sections in strict mode', [
                    'website_id' => $website->id,
                    'post_id' => $postId,
                    'dropped' => $dropped,
                ]);
            }
        }

        if ($sections === []) {
            return ['ok' => false, 'error' => 'no_sections'];
        }

        // How many of the available signals actually made it into the
        // generated output. Lets the UI explain "no internal links?" with
        // "the brief had 0 link targets to use" vs. "the model ignored
        // them" — we can tell because we know the input count and we can
        // count <a href> tags in the output.
        $linkCount = 0;
        foreach ($sections as $section) {
            $linkCount += preg_match_all('/<a\s+href=/i', (string) ($section['proposed_html'] ?? ''));
        }

        $result = [
            'ok' => true,
            'summary' => mb_substr((string) ($response['summary'] ?? ''), 0, 600),
            'sections' => $sections,
            'sources_used' => [
                'brief' => $hasBrief,
                'gaps' => $hasGaps,
                'content' => $hasContent,
            ],
            'diagnostics' => [
                'internal_links_available' => $availableInternalLinks,
                'internal_links_in_output' => $linkCount,
                'paa_questions_available' => $availablePaaCount,
                'gaps_available' => $availableGapsCount,
                'sections_returned' => count($sections),
            ],
            'cached' => false,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, self::CACHE_TTL_SEC);

        return $result;
    }

    /**
     * @param  list<array{url: string, anchor: string, topic: string, clicks: int}>  $smartLinks
     * @return list<array{role: string, content: string}>
     */
    private function buildPrompt(string $keyword, string $currentText, ?array $brief, ?array $gaps, bool $hasH1, array $smartLinks, bool $strictSelection): array
    {
        $briefBlock = '(none)';
        if (is_array($brief) && ! empty($brief)) {
            $subtopics = array_values(array_filter((array) ($brief['subtopics'] ?? []), 'is_string'));
            $entities = array_values(array_filter((array) ($brief['must_have_entities'] ?? []), 'is_string'));
            $outline = array_values(array_filter((array) ($brief['suggested_outline'] ?? []), 'is_string'));
            $paa = array_values(array_filter((array) ($brief['people_also_ask'] ?? []), 'is_string'));
            $serpTitles = array_slice(array_values(array_filter((array) ($brief['serp_titles'] ?? []), 'is_string')), 0, 10);
            $briefBlock = json_encode([
                'angle' => (string) ($brief['angle'] ?? ''),
                'recommended_word_count' => (int) ($brief['recommended_word_count'] ?? 0),
                'suggested_schema_type' => (string) ($brief['suggested_schema_type'] ?? ''),
                'subtopics' => $subtopics,
                'must_have_entities' => $entities,
                'suggested_outline' => $outline,
                'people_also_ask' => $paa,
                'top_serp_titles' => $serpTitles,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Topic-tagged internal links: each entry tells the model which
        // section topic this URL is the strongest fit for, derived from
        // the GSC query that drove the most impressions to that page.
        $smartLinksBlock = empty($smartLinks)
            ? '(none)'
            : json_encode(array_map(static fn (array $l) => [
                'url' => $l['url'],
                'anchor' => $l['anchor'],
                'best_fit_topic' => $l['topic'],
            ], $smartLinks), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $gapsBlock = '(none)';
        if (is_array($gaps) && ! empty($gaps)) {
            $missing = array_slice(array_filter((array) ($gaps['missing'] ?? []), 'is_array'), 0, 8);
            $missingNorm = array_map(static fn (array $m) => [
                'topic' => (string) ($m['topic'] ?? ''),
                'rationale' => (string) ($m['rationale'] ?? ''),
            ], $missing);
            $gapsBlock = json_encode($missingNorm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $currentBlock = $currentText !== ''
            ? mb_substr($currentText, 0, 24000)
            : '(empty post)';

        $system = <<<'SYS'
You are a senior SEO editor proposing publish-ready content changes the
user can review and approve one section at a time. Your job is to use
EVERY signal you're given — the brief's outline, must-have entities,
"people also ask" questions, top-SERP titles, internal-link targets,
and the topical-gap analysis — and turn them into reviewable sections.

Output rules (STRICT — non-compliance breaks the consumer):
- Return ONE JSON object only. No prose, no markdown fences, no commentary.
- Top-level keys: "summary" (string) and "sections" (array).
- Section count: %SECTION_COUNT_RULE%
- 200–500 words per section is the sweet spot; up to 800 when the topic
  genuinely warrants depth. Don't pad. Don't undersell.
- "people also ask" handling — REQUIRED: for EACH question in the
  brief's `people_also_ask` array, produce one section whose <h2> is the
  question itself (or a near-paraphrase). The first <p> after that <h2>
  must be a direct, snippet-ready answer (40–60 words, no preamble).
  Tag these with source_tags ⊇ ["brief"] (since PAA comes from the
  brief). Do NOT skip PAA questions — every one gets a section.
- For every topical-gap "missing" topic, produce a section that closes
  it. Tag with source_tags ⊇ ["gaps"].
- For every must-have entity, weave it naturally into at least one
  section (no entity stuffing — work it into prose where it fits).
- INTERNAL LINKS — REQUIRED. The user provides a topic-tagged list of
  internal_links. Each entry has:
    • url             — the page to link to
    • anchor          — suggested anchor text (paraphrase if it reads
                        more naturally; do not invent a different URL)
    • best_fit_topic  — the topic that page is the strongest fit for,
                        derived from the GSC query that drives the
                        most clicks to it
  For EVERY entry you MUST include exactly ONE <a href="<url>"><anchor
  or paraphrase></a> in the response. Place each link in the section
  whose subject most overlaps with the entry's best_fit_topic.
  %LINK_FALLBACK_RULE%
  Do not link to the same URL twice. Do not skip any entry.

  Concrete example: if internal_links contains
    [{"url":"https://example.com/protein-guide",
      "anchor":"vegan protein",
      "best_fit_topic":"vegan protein sources"}]
  then your section about protein sources must include something like
    <p>For an in-depth comparison see our
    <a href="https://example.com/protein-guide">vegan protein</a>
    guide.</p>
  Failing to include any internal_link is a failure of the task.
- proposed_html: valid HTML using <h1>, <h2>, <h3>, <p>, <ul>, <ol>,
  <li>, <strong>, <em>, <a>. No inline styles, no <script>, no markdown,
  no <html>/<body>/<head>.
- H1 RULE: the post says HAS_H1=%H1_FLAG%. When HAS_H1=false, the FIRST
  add (or the replace, if any) MUST start with one <h1> that includes
  the focus keyword naturally, IMMEDIATELY FOLLOWED by an intro <p>
  paragraph of 2–4 sentences that previews what the post covers and
  uses the focus keyword once. Only AFTER that intro paragraph may any
  <h2> appear. Never place an <h2> directly after an <h1> with no
  intervening body content. When HAS_H1=true, do NOT introduce another
  <h1> in any section.
- HEADING-AFTER-HEADING RULE: at no point should two heading tags
  (<h1>/<h2>/<h3>) appear back-to-back with no body content (<p>,
  <ul>, <ol>) between them. Every heading must be followed by at least
  one paragraph or list before the next heading.
- kind ∈ {"add","edit","replace"}.
   • "add"     — new section the post is missing. Lead with an <h2>.
   • "edit"    — rewrite an existing passage. current_html MUST be a
                 verbatim substring of the source post (copy-paste exact,
                 do not paraphrase). If you cannot copy a verbatim slice,
                 use kind="add" instead.
   • "replace" — full post replacement. %REPLACE_RULE%
- source_tags: non-empty subset of {"brief","gaps","content"} indicating
  which inputs drove the change.
- rationale: one short sentence — the reader benefit or ranking gain.
- title: one short phrase shown in the reviewer card.

Write in the same voice and reading level as the existing post when one is
provided. When the post is empty, default to clear, conversational expert
tone. Respect the brief's recommended depth and target keyword
prominence — the focus keyword should appear naturally in the H1 (when
you generate one) or the opening paragraph of any "add" section, and
again in at least one <h2> within the first three sections.

%SCARCITY_FALLBACK%
SYS;
        $sectionCountRule = $strictSelection
            ? "STRICT-SELECTION MODE — the user curated their inputs in a prior step. Follow these rules exactly:\n  (1) Count N = total items in the brief's `subtopics` + `people_also_ask` arrays + the gap analysis's `missing` array. (Do NOT count `must_have_entities`, `top_serp_titles`, or internal_links — those are CONTEXT, not section drivers.)\n  (2) Generate EXACTLY N sections of kind=\"add\", one per input item, in input order. Each section's <h2> uses or closely paraphrases its source item.\n  (3) NEVER use kind=\"replace\" in strict mode, EVEN WHEN THE POST IS EMPTY. Always emit N add sections instead.\n  (4) Do NOT invent new topics, do NOT pad with extra sections, do NOT split an input into multiple sections, do NOT merge two inputs into one section. One input → one section.\n  (5) Optionally append `edit` sections for weak passages of the existing post (improvements). These do NOT count toward N and are extra, not substitutes."
            : 'BETWEEN 12 AND 20 sections. Coverage is the point — produce one section per brief subtopic, one per topical gap, and one per "people also ask" question. Combining is allowed only when two inputs cover the same ground; otherwise each gets its own section. Returning fewer than 12 sections when richer inputs are available is a failure of the task.';

        $linkFallbackRule = $strictSelection
            ? 'If no section is a clean fit, place the link in the closest-related section anyway — DO NOT invent a new section to host the link in strict mode.'
            : 'If no section is a clean fit, create one (an "add" section about that topic) so the link has a home — that\'s preferable to dropping the entry.';

        $replaceRule = $strictSelection
            ? 'FORBIDDEN in strict-selection mode — never emit kind="replace" here. If the post is empty, still produce N "add" sections per the rule above.'
            : 'Use ONLY when the post is empty or fundamentally unsalvageable. Maximum one per response.';

        $scarcityFallback = $strictSelection
            ? '' // no fallback paragraph in strict mode — STRICT-SELECTION rule already covers it
            : "INPUT-SCARCITY FALLBACK: when CONTENT BRIEF, TOPICAL GAPS, and CURRENT\nPOST CONTENT are ALL marked \"(none)\" / \"(empty post)\", you still produce\na useful first draft using only the target keyword and any user-curated\nitems in the user message. In that case, return ONE \"replace\" section\nthat scaffolds a complete article: H1 (if not yet on page) → 2–4\nsentence intro paragraph → 6–10 H2 sections covering the keyword's\ntypical search intent (problem → core explanation → how-to / comparison\n→ FAQs → next steps), each followed by 2–4 paragraphs of substantive\nprose. Do NOT refuse — the absence of brief / gaps means richer output\nis impossible, not that no output is.";

        $system = str_replace(
            ['%H1_FLAG%', '%SECTION_COUNT_RULE%', '%LINK_FALLBACK_RULE%', '%REPLACE_RULE%', '%SCARCITY_FALLBACK%'],
            [$hasH1 ? 'true' : 'false', $sectionCountRule, $linkFallbackRule, $replaceRule, $scarcityFallback],
            $system,
        );

        $user = <<<USER
Target keyword: "{$keyword}"

CONTENT BRIEF (may be empty):
{$briefBlock}

INTERNAL LINKS — topic-tagged candidates pulled from this site's own GSC
footprint, ordered by topical fit (may be empty):
{$smartLinksBlock}

TOPICAL GAPS vs. top SERP (may be empty):
{$gapsBlock}

CURRENT POST CONTENT (plain text extract, may be empty):
{$currentBlock}

Return JSON exactly in this shape:
{
  "summary": "One short paragraph: what the proposed changes do as a whole.",
  "sections": [
    {
      "title": "Section title shown in the reviewer UI",
      "kind": "add" | "edit" | "replace",
      "anchor": "for kind=edit only — short heading or first words of the targeted block; null otherwise",
      "current_html": "for kind=edit only — verbatim substring of the post being replaced; null otherwise",
      "proposed_html": "<h2>...</h2><p>...</p>",
      "rationale": "One sentence on why this change matters.",
      "source_tags": ["brief"]
    }
  ]
}
USER;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * Apply the user's curated selection (from the plan step) to the
     * brief / gaps blobs. Anything the user didn't tick is removed, so
     * the writer prompt only mentions what the user wants.
     *
     *   selected = {
     *     h1?: string,
     *     h2_outline?: string[],
     *     subtopics?: string[],
     *     paa?: string[],
     *     gap_topics?: string[],
     *     competitor_subtopics?: string[],
     *   }
     *
     * If a selection key is absent OR an empty array, that signal stays
     * intact (treated as "user didn't curate this list"). If a selection
     * key is a non-empty array, the corresponding brief/gaps list is
     * filtered to that exact set.
     *
     * @return array{0: ?array<string,mixed>, 1: ?array<string,mixed>}
     */
    private function applySelection(?array $brief, ?array $gaps, array $selected): array
    {
        $brief = is_array($brief) ? $brief : null;
        $gaps = is_array($gaps) ? $gaps : null;

        $filterList = static function (?array $list, $picked): ?array {
            if (! is_array($list)) {
                return $list;
            }
            if (! is_array($picked) || empty($picked)) {
                return $list;
            }
            $set = [];
            foreach ($picked as $p) {
                if (is_string($p) && trim($p) !== '') {
                    $set[mb_strtolower(trim($p))] = true;
                }
            }
            if (empty($set)) {
                return $list;
            }
            $filtered = [];
            foreach ($list as $item) {
                if (! is_string($item)) {
                    continue;
                }
                if (isset($set[mb_strtolower(trim($item))])) {
                    $filtered[] = $item;
                }
            }
            return $filtered;
        };

        if ($brief !== null) {
            // Suggested H1 — when the user picked one (or wrote a custom),
            // overwrite the brief's value so the prompt's H1 rule lands
            // on what they chose.
            if (isset($selected['h1']) && is_string($selected['h1']) && trim($selected['h1']) !== '') {
                $brief['suggested_h1'] = trim($selected['h1']);
            }
            $brief['suggested_outline'] = $filterList($brief['suggested_outline'] ?? [], $selected['h2_outline'] ?? null);
            $brief['subtopics']         = $filterList($brief['subtopics'] ?? [], $selected['subtopics'] ?? null);
            $brief['people_also_ask']   = $filterList($brief['people_also_ask'] ?? [], $selected['paa'] ?? null);
        }

        if ($gaps !== null) {
            // Gap "missing" entries are objects {topic, rationale}; filter
            // by topic match. Competitor subtopics are plain strings.
            if (is_array($selected['gap_topics'] ?? null) && ! empty($selected['gap_topics'])) {
                $picks = [];
                foreach ((array) $selected['gap_topics'] as $p) {
                    if (is_string($p) && trim($p) !== '') {
                        $picks[mb_strtolower(trim($p))] = true;
                    }
                }
                if (! empty($picks) && is_array($gaps['missing'] ?? null)) {
                    $gaps['missing'] = array_values(array_filter(
                        $gaps['missing'],
                        static fn ($m) => is_array($m)
                            && is_string($m['topic'] ?? null)
                            && isset($picks[mb_strtolower(trim($m['topic']))]),
                    ));
                }
            }
            $gaps['competitor_subtopics'] = $filterList(
                $gaps['competitor_subtopics'] ?? [],
                $selected['competitor_subtopics'] ?? null,
            );
        }

        return [$brief, $gaps];
    }

    /**
     * Build a topic-tagged internal-link candidate pool.
     *
     * Two sources, layered:
     *   1) GSC click data — the EBQ moat. Pages with proven user demand
     *      always rank highest. Score = clicks × (1 + topic_overlap).
     *   2) WordPress pages provided by the plugin (wp_pages) — every
     *      published post/page on the site, including newly-published
     *      ones that have zero GSC traction yet. Score uses a tiny
     *      synthetic signal so these only surface when topic overlap is
     *      strong AND no GSC candidate covers the same topic better.
     *
     * Dedup by URL: if a URL appears in both, the GSC entry wins (preserves
     * its click signal and best-matching anchor from real search queries).
     *
     * Returns up to 12 candidates as
     *   [{url, anchor, topic, source, clicks}, ...]
     * sorted by score desc.
     *
     * Excludes the current post's URL so we don't link the page to itself.
     *
     * @param  list<array{url: string, title?: string}>  $wpPages
     * @return list<array{url: string, anchor: string, topic: string, source: string, clicks: int}>
     */
    private function resolveSmartInternalLinks(
        Website $website,
        string $excludeUrl,
        string $focusKeyword,
        ?array $brief,
        ?array $gaps,
        array $wpPages = [],
    ): array {
        // Build a list of (topic_label, tokens) pairs. Each candidate URL
        // is then matched against whichever topic shares the most tokens
        // with its top GSC query.
        $topics = [];

        $addTopic = function (string $label) use (&$topics) {
            $label = trim($label);
            if ($label === '') {
                return;
            }
            $tokens = $this->significantTokens($label);
            if ($tokens === []) {
                return;
            }
            $topics[] = ['label' => $label, 'tokens' => $tokens];
        };

        $addTopic($focusKeyword);
        if (is_array($brief)) {
            foreach (array_slice((array) ($brief['subtopics'] ?? []), 0, 20) as $t) {
                if (is_string($t)) {
                    $addTopic($t);
                }
            }
            foreach (array_slice((array) ($brief['people_also_ask'] ?? []), 0, 20) as $t) {
                if (is_string($t)) {
                    $addTopic($t);
                }
            }
            foreach (array_slice((array) ($brief['must_have_entities'] ?? []), 0, 20) as $t) {
                if (is_string($t)) {
                    $addTopic($t);
                }
            }
        }
        if (is_array($gaps)) {
            foreach (array_slice((array) ($gaps['missing'] ?? []), 0, 20) as $row) {
                if (is_array($row) && is_string($row['topic'] ?? null)) {
                    $addTopic((string) $row['topic']);
                }
            }
        }

        if ($topics === []) {
            return [];
        }

        // Union of all tokens across topics — the WHERE clause that pulls
        // candidate rows. We score per-topic in PHP afterwards, so over-
        // pulling here is fine.
        $allTokens = [];
        foreach ($topics as $t) {
            foreach ($t['tokens'] as $tok) {
                $allTokens[$tok] = true;
            }
        }
        $allTokens = array_keys($allTokens);

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
                ->when($excludeUrl !== '', fn ($q) => $q->where('page', '!=', $excludeUrl))
                ->where(function ($w) use ($allTokens) {
                    foreach ($allTokens as $tok) {
                        $w->orWhere('query', 'LIKE', '%'.$tok.'%');
                    }
                })
                ->selectRaw('page, query, SUM(clicks) AS clicks, SUM(impressions) AS impressions')
                ->groupBy('page', 'query')
                ->orderByDesc('clicks')
                ->limit(200)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('AiWriterService: smart internal link lookup failed', [
                'website_id' => $website->id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        // Score each (page, query) row against every topic; whichever
        // topic shares the most tokens with the query becomes that row's
        // best_fit_topic. Then keep the highest-scoring row per page.
        $best = [];
        foreach ($rows as $r) {
            $page = (string) $r->page;
            $query = (string) $r->query;
            $clicks = (int) $r->clicks;
            $impressions = (int) $r->impressions;
            $signal = $clicks > 0 ? $clicks : max(1, (int) round($impressions / 10));

            $queryLower = mb_strtolower($query);
            $bestTopic = null;
            $bestOverlap = 0;
            foreach ($topics as $t) {
                $overlap = 0;
                foreach ($t['tokens'] as $tok) {
                    if ($tok !== '' && str_contains($queryLower, $tok)) {
                        $overlap++;
                    }
                }
                if ($overlap > $bestOverlap) {
                    $bestOverlap = $overlap;
                    $bestTopic = $t['label'];
                }
            }
            if ($bestTopic === null) {
                continue;
            }

            $score = $signal * (1 + $bestOverlap);

            if (! isset($best[$page]) || $best[$page]['score'] < $score) {
                $best[$page] = [
                    'url' => $page,
                    'anchor' => $query,
                    'topic' => $bestTopic,
                    'source' => 'gsc',
                    'clicks' => $clicks,
                    'score' => $score,
                ];
            }
        }

        // WordPress fallback layer. Each plugin-supplied page is matched
        // against topics by its TITLE, with a low synthetic signal so it
        // only beats a GSC entry when overlap is dramatically stronger.
        // GSC dedup wins: if a URL is already keyed in $best, skip it.
        // ALL_TOKENS_MATCH bonus rewards titles whose tokens are entirely
        // contained in a topic, which captures cases like a post titled
        // "Vegan Protein Sources" matching the topic "vegan protein".
        foreach ($wpPages as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = trim((string) ($row['url'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            if ($url === '' || $title === '' || $url === $excludeUrl || isset($best[$url])) {
                continue;
            }
            $titleLower = mb_strtolower($title);
            $bestTopic = null;
            $bestOverlap = 0;
            foreach ($topics as $t) {
                $overlap = 0;
                foreach ($t['tokens'] as $tok) {
                    if ($tok !== '' && str_contains($titleLower, $tok)) {
                        $overlap++;
                    }
                }
                if ($overlap > $bestOverlap) {
                    $bestOverlap = $overlap;
                    $bestTopic = $t['label'];
                }
            }
            if ($bestTopic === null || $bestOverlap === 0) {
                continue;
            }

            // Synthetic signal — well below typical click counts so a
            // GSC-backed candidate with even modest clicks outranks a
            // perfect-overlap WP page. The 2× overlap multiplier keeps
            // strongly-matching new content competitive.
            $score = 1 + (2 * $bestOverlap);

            $best[$url] = [
                'url' => $url,
                'anchor' => $title,
                'topic' => $bestTopic,
                'source' => 'wp',
                'clicks' => 0,
                'score' => $score,
            ];
        }

        uasort($best, static fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $out = [];
        foreach ($best as $row) {
            unset($row['score']);
            $out[] = $row;
            if (count($out) >= 12) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function significantTokens(string $text): array
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/[^a-z0-9]+/u', $text) ?: [];
        $stop = [
            'the', 'and', 'for', 'with', 'a', 'an', 'of', 'to', 'in', 'on',
            'is', 'are', 'or', 'be', 'do', 'does', 'how', 'what', 'why',
            'when', 'where', 'who', 'which', 'can', 'will', 'should',
            'this', 'that', 'these', 'those', 'has', 'have', 'had',
            'as', 'at', 'by', 'it', 'its',
        ];
        $out = [];
        foreach ($parts as $p) {
            if (mb_strlen($p) >= 3 && ! in_array($p, $stop, true)) {
                $out[$p] = true;
            }
            if (count($out) >= 6) {
                break;
            }
        }

        return array_keys($out);
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<array<string, mixed>>
     */
    private function normalizeSections(array $raw): array
    {
        $out = [];
        $allowedKinds = ['add', 'edit', 'replace'];
        $allowedTags = ['brief', 'gaps', 'content'];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $kind = strtolower(trim((string) ($item['kind'] ?? '')));
            if (! in_array($kind, $allowedKinds, true)) {
                continue;
            }
            $proposed = trim((string) ($item['proposed_html'] ?? ''));
            if ($proposed === '') {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                $title = $kind === 'add'
                    ? 'New section'
                    : ($kind === 'edit' ? 'Edit existing section' : 'Replace post content');
            }

            $currentHtml = $kind === 'edit' ? trim((string) ($item['current_html'] ?? '')) : '';
            $anchor = $kind === 'edit' ? trim((string) ($item['anchor'] ?? '')) : '';

            $tags = [];
            $rawTags = $item['source_tags'] ?? [];
            if (is_array($rawTags)) {
                foreach ($rawTags as $t) {
                    if (! is_string($t)) {
                        continue;
                    }
                    $t = strtolower(trim($t));
                    if (in_array($t, $allowedTags, true) && ! in_array($t, $tags, true)) {
                        $tags[] = $t;
                    }
                }
            }

            $out[] = [
                'id' => 's_'.substr(hash('xxh3', $kind.'|'.$title.'|'.$proposed), 0, 10),
                'title' => mb_substr($title, 0, 120),
                'kind' => $kind,
                'anchor' => $anchor !== '' ? mb_substr($anchor, 0, 200) : null,
                'current_html' => $currentHtml !== '' ? mb_substr($currentHtml, 0, self::SECTION_HTML_CAP) : null,
                'proposed_html' => mb_substr($proposed, 0, self::SECTION_HTML_CAP),
                'rationale' => mb_substr(trim((string) ($item['rationale'] ?? '')), 0, 400),
                'source_tags' => $tags,
            ];

            if (count($out) >= self::MAX_SECTIONS) {
                break;
            }
        }

        return $out;
    }
}
