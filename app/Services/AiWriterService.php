<?php

namespace App\Services;

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
     * 6 sections × up to 600 words each ≈ 4.5–6k output tokens, comfortably
     * under the 8k `max_tokens` budget below. Bumped from the earlier
     * conservative cap (5×250 words) so writers can ship richer proposals
     * without hitting the truncation edge case that produces
     * `llm_parse_failed`.
     */
    private const MAX_SECTIONS = 6;

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

        $hasBrief = $brief !== null && ! empty($brief['subtopics'] ?? []);
        $hasGaps = $gaps !== null && ! empty($gaps['missing'] ?? []);
        $hasContent = mb_strlen($currentText) >= 100;
        // Look at the raw HTML (block markers and all) for an <h1> — the
        // strip_tags'd $currentText loses tag info. Editor themes render the
        // post title as H1, so most published posts won't have one inside the
        // content; the writer should add one when missing.
        $hasH1 = (bool) preg_match('/<h1\b/i', (string) ($input['current_html'] ?? ''));

        if (! $hasBrief && ! $hasGaps && ! $hasContent) {
            return [
                'ok' => false,
                'error' => 'no_inputs',
                'message' => 'Generate a brief, run topical-gap analysis, or write some content first — the AI writer needs at least one to work from.',
            ];
        }

        $cacheKey = sprintf(
            'ai_writer_v1:%d:%d:%s:%s:%s:%s',
            $website->id,
            $postId,
            hash('xxh3', mb_strtolower($keyword)),
            $hasBrief ? '1' : '0',
            $hasGaps ? '1' : '0',
            substr(hash('sha256', $currentText), 0, 12),
        );
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['ok'] ?? false) === true) {
            $cached['cached'] = true;

            return $cached;
        }

        $messages = $this->buildPrompt($keyword, $currentText, $brief, $gaps, $hasH1);
        // 8000 tokens ≈ 6000 words. Lets the model deliver fuller, ready-
        // to-publish sections without hitting the mid-JSON truncation that
        // surfaces as `llm_parse_failed`. Mistral Small supports up to 32k
        // context, so this is well within budget.
        $response = $this->llm->completeJson($messages, [
            'temperature' => 0.5,
            'max_tokens' => 8000,
            'json_object' => true,
            'timeout' => 90,
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
        if ($sections === []) {
            return ['ok' => false, 'error' => 'no_sections'];
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
            'cached' => false,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, self::CACHE_TTL_SEC);

        return $result;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildPrompt(string $keyword, string $currentText, ?array $brief, ?array $gaps, bool $hasH1): array
    {
        $briefBlock = '(none)';
        if (is_array($brief) && ! empty($brief)) {
            $subtopics = array_slice(array_filter((array) ($brief['subtopics'] ?? []), 'is_string'), 0, 12);
            $entities = array_slice(array_filter((array) ($brief['must_have_entities'] ?? []), 'is_string'), 0, 8);
            $outline = array_slice(array_filter((array) ($brief['suggested_outline'] ?? []), 'is_string'), 0, 8);
            $paa = array_slice(array_filter((array) ($brief['people_also_ask'] ?? []), 'is_string'), 0, 6);
            $briefBlock = json_encode([
                'angle' => (string) ($brief['angle'] ?? ''),
                'recommended_word_count' => (int) ($brief['recommended_word_count'] ?? 0),
                'subtopics' => $subtopics,
                'must_have_entities' => $entities,
                'suggested_outline' => $outline,
                'people_also_ask' => $paa,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

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
user can review and approve one section at a time.

Output rules (STRICT — non-compliance breaks the consumer):
- Return ONE JSON object only. No prose, no markdown fences, no commentary.
- Top-level keys: "summary" (string) and "sections" (array).
- Generate UP TO 6 sections. Aim for substantive, ready-to-ship sections,
  not stubs. 300–600 words of body per section is ideal; up to 900 if the
  topic genuinely warrants depth. Don't pad — but don't shy away from
  detail when the brief or gap topic deserves it.
- proposed_html: valid HTML using <h1>, <h2>, <h3>, <p>, <ul>, <ol>,
  <li>, <strong>, <em>, <a>. No inline styles, no <script>, no markdown,
  no <html>/<body>/<head>.
- H1 RULE: the post says HAS_H1=%H1_FLAG%. When HAS_H1=false, the FIRST
  add (or the replace, if any) MUST start with one <h1> that includes the
  focus keyword naturally. When HAS_H1=true, do NOT introduce another
  <h1> in any section.
- kind ∈ {"add","edit","replace"}.
   • "add"     — new section the post is missing. Lead with an <h2>.
   • "edit"    — rewrite an existing passage. current_html MUST be a
                 verbatim substring of the source post (copy-paste exact,
                 do not paraphrase). If you cannot copy a verbatim slice,
                 use kind="add" instead.
   • "replace" — full post replacement. Use ONLY when the post is empty
                 or fundamentally unsalvageable. Maximum one per response.
- source_tags: non-empty subset of {"brief","gaps","content"} indicating
  which inputs drove the change.
- rationale: one short sentence — the reader benefit or ranking gain.
- title: one short phrase shown in the reviewer card.

Write in the same voice and reading level as the existing post when one is
provided. When the post is empty, default to clear, conversational expert
tone. Always respect the brief's recommended depth and target keyword
prominence — the focus keyword should appear naturally in the H1 (when
you generate one) or in the opening paragraph of any "add" section.
SYS;
        $system = str_replace('%H1_FLAG%', $hasH1 ? 'true' : 'false', $system);

        $user = <<<USER
Target keyword: "{$keyword}"

CONTENT BRIEF (may be empty):
{$briefBlock}

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
