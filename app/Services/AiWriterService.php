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
     * Capped at 5 (was 8). With each section returning 100–300 words of
     * HTML plus title + rationale + tags, 5 sections ≈ 2.5–4k output
     * tokens which fits comfortably under the model's `max_tokens` budget
     * without truncating the JSON mid-output.
     */
    private const MAX_SECTIONS = 5;

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

        $messages = $this->buildPrompt($keyword, $currentText, $brief, $gaps);
        // 4000 tokens = ~3000 words output. Enough headroom for 5 sections
        // each with a heading + 200-word body + rationale + tags + JSON
        // delimiters. Anything tighter risks the model truncating mid-JSON,
        // which trips strict decode → `llm_parse_failed`.
        $response = $this->llm->completeJson($messages, [
            'temperature' => 0.5,
            'max_tokens' => 4000,
            'json_object' => true,
            'timeout' => 60,
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
    private function buildPrompt(string $keyword, string $currentText, ?array $brief, ?array $gaps): array
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
            ? mb_substr($currentText, 0, 12000)
            : '(empty post)';

        $system = <<<'SYS'
You are a senior SEO editor proposing surgical, reviewable content changes.

Output rules (STRICT — non-compliance breaks the consumer):
- Return ONE JSON object only. No prose, no markdown fences, no commentary.
- Top-level keys: "summary" (string) and "sections" (array).
- Generate AT MOST 5 sections. Fewer is better than padded.
- proposed_html: valid HTML, ≤ 250 words per section, only <h2>, <h3>,
  <p>, <ul>, <ol>, <li>, <strong>, <em>, <a> tags. No inline styles,
  no <script>, no markdown.
- kind ∈ {"add","edit","replace"}.
   • "add"     — new section the post is missing.
   • "edit"    — rewrite an existing passage. current_html MUST be a
                 verbatim substring of the post (copy-paste exact, do
                 not paraphrase). If you cannot copy a verbatim slice,
                 use kind="add" instead.
   • "replace" — full post replacement. Use ONLY when the post is empty
                 or fundamentally unsalvageable. Maximum one per response.
- source_tags: subset of {"brief","gaps","content"} indicating which
  inputs drove the change.
- rationale: one short sentence.
- title: one short phrase shown to the reviewer.

If the post is empty, return a single "replace" section structured around
the brief's outline / gap topics.
If brief and gaps are both missing, propose "edit" sections that tighten
weak passages of the existing post.
SYS;

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
                'current_html' => $currentHtml !== '' ? mb_substr($currentHtml, 0, 6000) : null,
                'proposed_html' => mb_substr($proposed, 0, 8000),
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
