<?php

namespace App\Services;

use App\Services\Llm\LlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AI-powered title + meta description rewrites for the live editor.
 *
 * Two modes:
 *   - **Auto** (no intent passed) — returns 3 rewrites across DIFFERENT
 *     angles, like the original behaviour. Good "show me what's possible"
 *     mode for users who don't know which angle they want.
 *   - **Single intent** (intent key passed) — returns 3 *variations* of
 *     that one intent. Different headlines, hooks, lengths — all still
 *     within the angle the user picked. Cached separately per intent so
 *     the user can A/B between intents without re-burning LLM tokens.
 *
 * The intent registry below documents every angle with a one-line
 * description + a strict structural rule the model must follow when that
 * intent is selected (e.g. list-based titles must start with a number).
 *
 * Caching
 * ───────
 * Caches per (post_id × content-hash × focus-keyword × top-3-hash × intent)
 * for 7 days. Re-clicks within a week are free. Each intent has its own
 * cache slot so flipping intents doesn't invalidate prior generations.
 *
 * MOAT
 * ────
 * Prompt + few-shot examples + competitor SERP grounding all live on EBQ.
 * Plugin only sees rewrites + rationale text. Free-tier uninstall = lose
 * the only "make my snippet better" button in the editor.
 */
class AiSnippetRewriterService
{
    private const CACHE_TTL_DAYS = 7;

    /** Sentinel for the "give me a mixed-angle set" mode. */
    public const INTENT_AUTO = 'auto';

    /**
     * Curated CTR-boosting "power words" the model can lean on when a
     * title/meta would otherwise read flat. Grouped here for legibility,
     * but the prompt sees them as a single shuffled subset so the model
     * doesn't bias toward any one bucket.
     *
     * Picked for SEO snippet relevance — words that show up in click-heavy
     * SERP titles and read as natural English. We deliberately exclude
     * over-used clickbait ("INSANE", "SHOCKING", "NEVER BEFORE SEEN")
     * because Google penalizes title-tag clickbait via the rewrite layer.
     *
     * @var list<string>
     */
    private const POWER_WORDS = [
        // Value / outcome
        'proven', 'effective', 'practical', 'actionable', 'real',
        'powerful', 'reliable', 'trusted', 'guaranteed',

        // Speed / ease
        'fast', 'quick', 'simple', 'easy', 'straightforward',
        'instant', 'in minutes', 'step-by-step',

        // Comprehensiveness / authority
        'complete', 'definitive', 'ultimate', 'essential', 'comprehensive',
        'expert', 'tested', 'data-driven', 'researched',

        // Curiosity / contrast
        'avoid', 'mistake', 'truth', 'overlooked', 'forgotten',
        'underrated', 'real reason', 'actually',

        // Currency / freshness
        'updated', 'latest', 'new', '2026', 'modern',

        // Specificity hooks
        'every', 'top', 'best', 'free', 'exclusive',
        'beginner', 'advanced', 'pro', 'small-team',

        // Action verbs that read well in titles
        'master', 'boost', 'cut', 'fix', 'unlock',
        'save', 'speed up', 'rank', 'win', 'grow',
    ];

    /**
     * Intent registry. Each value:
     *   - label    : short human label for the picker UI
     *   - desc     : one-line description (also shown as picker tooltip)
     *   - rule     : the structural rule the model must follow when this
     *                intent is the single requested angle. Strict enough
     *                that 3 variations within the intent stay coherent.
     *
     * @var array<string, array{label: string, desc: string, rule: string}>
     */
    public const INTENTS = [
        // — User-requested 10 —
        'list_based' => [
            'label' => 'List-based',
            'desc'  => 'Listicle / "Top N" framing — scannable and high-CTR for comparison queries.',
            'rule'  => 'Title MUST start with a number ("7", "12", etc.) followed by a noun phrase. Meta MUST hint at what the list contains.',
        ],
        'problem_solution' => [
            'label' => 'Problem → Solution',
            'desc'  => 'Names the pain in the title, promises relief in the meta. Direct-response framing.',
            'rule'  => 'Title MUST name a concrete pain or mistake. Meta MUST present the page as the resolution in one short sentence.',
        ],
        'beginner_friendly' => [
            'label' => 'Beginner-friendly',
            'desc'  => 'Wide-funnel, gentle entry point. Targets "for beginners" / "explained simply" intent.',
            'rule'  => 'Title MUST contain a beginner cue ("for beginners", "explained", "from scratch", "step by step"). Meta MUST avoid jargon and promise plain language.',
        ],
        'question_based' => [
            'label' => 'Question-based',
            'desc'  => 'Featured-snippet + voice-search optimized. Title is the question; meta is the one-line answer + expansion.',
            'rule'  => 'Title MUST be a complete question ending with "?". Meta MUST start with a direct one-sentence answer, then a benefit hook.',
        ],
        'benefit_driven' => [
            'label' => 'Benefit-driven',
            'desc'  => 'Leads with the outcome the reader gets, not the topic. Best for solution-aware audiences.',
            'rule'  => 'Title MUST lead with the benefit ("Get…", "Cut…", "Save…", "Boost…", or a specific result). Meta MUST quantify the benefit if possible.',
        ],
        'authority_expert' => [
            'label' => 'Authority / expert',
            'desc'  => 'Trust-signal framing. Lab-tested, expert-reviewed, by-the-numbers. Best for YMYL topics.',
            'rule'  => 'Title MUST signal expertise or testing ("We tested", "Lab-verified", "By experts", "X data points analyzed"). Meta MUST reinforce with a credential cue.',
        ],
        'freshness_updated' => [
            'label' => 'Freshness / updated',
            'desc'  => 'Recency-CTR play — current year + "updated" / "new for". Best for fast-moving topics.',
            'rule'  => 'Title MUST contain the current year or "Updated" / "New for". Meta MUST signal what was refreshed (data, picks, methods).',
        ],
        'use_case_focused' => [
            'label' => 'Use-case focused',
            'desc'  => 'Niches the title to a specific use-case (industry, role, scenario). Lower volume, higher CTR.',
            'rule'  => 'Title MUST narrow to a use case ("for SaaS", "for ecommerce", "for remote teams", "for beginners running ads"). Meta MUST reinforce the niche fit.',
        ],
        'myth_busting' => [
            'label' => 'Myth-busting',
            'desc'  => 'Contrarian hook — "X isn\'t true / Y is the real answer". Pattern-interrupt for saturated SERPs.',
            'rule'  => 'Title MUST contain a contradiction or "isn\'t / not / wrong" hook. Meta MUST tease the corrected truth without spoiling the page.',
        ],
        'comparison_verdict' => [
            'label' => 'Comparison with verdict',
            'desc'  => 'X vs Y framing with a clear winner. Decisive comparison — beats wishy-washy "X vs Y compared" titles.',
            'rule'  => 'Title MUST contain "vs" or "or" between two named options AND signal a verdict ("which wins", "we picked", "the clear winner"). Meta MUST hint at the verdict\'s reasoning.',
        ],

        // — Existing 5 angles, kept for back-compat with old "Auto" output —
        'commercial' => [
            'label' => 'Commercial',
            'desc'  => 'Buyer-intent framing. Best for product / review / "best of" pages.',
            'rule'  => 'Title MUST signal commercial intent (best, top, review, buying). Meta MUST hint at decision-helping content.',
        ],
        'informational' => [
            'label' => 'Informational',
            'desc'  => 'Educational, encyclopedic. Wide-funnel learning intent.',
            'rule'  => 'Title MUST signal teaching ("What is", "Guide", "Explained"). Meta MUST promise comprehensive coverage.',
        ],
        'curiosity' => [
            'label' => 'Curiosity',
            'desc'  => 'Open-loop hook — withhold the answer slightly to drive the click. Use sparingly.',
            'rule'  => 'Title MUST create an information gap (surprising claim, counterintuitive frame). Meta MUST tease the resolution without revealing it.',
        ],
        'guide' => [
            'label' => 'Guide',
            'desc'  => 'Definitive long-form how-to. Pairs with deep-dive content.',
            'rule'  => 'Title MUST contain "Guide" / "Complete" / "Definitive" / "How to". Meta MUST signal step-by-step or comprehensive scope.',
        ],
        'comparison' => [
            'label' => 'Comparison',
            'desc'  => 'Side-by-side without taking a side. Good for objective review pages.',
            'rule'  => 'Title MUST name 2+ options being compared. Meta MUST signal balanced criteria.',
        ],
    ];

    public function __construct(private readonly LlmClient $llm) {}

    /** @return list<string> */
    public static function intentKeys(): array
    {
        return array_keys(self::INTENTS);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   ok: bool,
     *   intent?: string,
     *   rewrites?: list<array{title: string, meta: string, rationale: string, angle: string}>,
     *   model?: string,
     *   cached?: bool,
     *   error?: string,
     * }
     */
    public function rewrite(int $postId, array $input): array
    {
        if (! $this->llm->isAvailable()) {
            return ['ok' => false, 'error' => 'llm_not_configured'];
        }

        $focusKeyword = trim((string) ($input['focus_keyword'] ?? ''));
        $currentTitle = trim((string) ($input['current_title'] ?? ''));
        $currentMeta = trim((string) ($input['current_meta'] ?? ''));
        $contentExcerpt = mb_substr(trim((string) ($input['content_excerpt'] ?? '')), 0, 4000);
        $competitorTitles = is_array($input['competitor_titles'] ?? null) ? $input['competitor_titles'] : [];
        $competitorTitles = array_slice(array_values(array_filter(array_map('strval', $competitorTitles))), 0, 3);
        $additionalKeywords = is_array($input['additional_keywords'] ?? null) ? $input['additional_keywords'] : [];
        $additionalKeywords = array_values(array_unique(array_filter(
            array_map(static fn ($v) => trim((string) $v), $additionalKeywords),
            static fn (string $v) => $v !== '' && mb_strtolower($v) !== mb_strtolower($focusKeyword)
        )));
        $additionalKeywords = array_slice($additionalKeywords, 0, 10);

        // Validate / normalize the requested intent. Anything not in the
        // registry collapses to AUTO so a stale client can't crash us.
        $intent = (string) ($input['intent'] ?? self::INTENT_AUTO);
        if ($intent !== self::INTENT_AUTO && ! array_key_exists($intent, self::INTENTS)) {
            $intent = self::INTENT_AUTO;
        }

        if ($focusKeyword === '') {
            return ['ok' => false, 'error' => 'missing_focus_keyword'];
        }
        if ($contentExcerpt === '') {
            return ['ok' => false, 'error' => 'content_too_short'];
        }

        $cacheKey = $this->cacheKey($postId, $focusKeyword, $currentTitle, $currentMeta, $contentExcerpt, $competitorTitles, $intent, $additionalKeywords);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $cached['cached'] = true;
            return $cached;
        }

        $messages = $this->buildPrompt($focusKeyword, $currentTitle, $currentMeta, $contentExcerpt, $competitorTitles, $intent, $additionalKeywords);
        $payload = $this->llm->completeJson($messages, [
            'temperature' => 0.7,
            'max_tokens' => 1100,
            'json_object' => true,
            'timeout' => 30,
        ]);

        if (! is_array($payload) || ! isset($payload['rewrites']) || ! is_array($payload['rewrites'])) {
            Log::warning('AiSnippetRewriterService: malformed LLM response', [
                'post_id' => $postId,
                'focus_keyword' => $focusKeyword,
                'intent' => $intent,
            ]);
            return ['ok' => false, 'error' => 'llm_parse_failed'];
        }

        // Default angle to the requested intent in single-intent mode so
        // the UI can still chip-tag rewrites even if the model omits it.
        $defaultAngle = $intent !== self::INTENT_AUTO ? $intent : 'general';

        $rewrites = [];
        foreach ($payload['rewrites'] as $r) {
            if (! is_array($r)) continue;
            $title = trim((string) ($r['title'] ?? ''));
            $meta = trim((string) ($r['meta'] ?? ''));
            if ($title === '' || $meta === '') continue;
            $rewrites[] = [
                'title' => mb_substr($title, 0, 90),
                'meta' => mb_substr($meta, 0, 200),
                'rationale' => mb_substr(trim((string) ($r['rationale'] ?? '')), 0, 220),
                'angle' => mb_substr(trim((string) ($r['angle'] ?? $defaultAngle)), 0, 32),
            ];
            if (count($rewrites) >= 3) break;
        }

        if ($rewrites === []) {
            return ['ok' => false, 'error' => 'no_rewrites_returned'];
        }

        $result = [
            'ok' => true,
            'intent' => $intent,
            'rewrites' => $rewrites,
            'model' => 'mistral',
            'cached' => false,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, Carbon::now()->addDays(self::CACHE_TTL_DAYS));
        return $result;
    }

    /**
     * @param  list<string>  $competitorTitles
     * @param  list<string>  $additionalKeywords
     * @return list<array{role: string, content: string}>
     */
    private function buildPrompt(string $keyword, string $currentTitle, string $currentMeta, string $excerpt, array $competitorTitles, string $intent, array $additionalKeywords = []): array
    {
        $competitorBlock = $competitorTitles === []
            ? "(no competitor titles available)"
            : implode("\n", array_map(fn ($t, $i) => sprintf('  %d. %s', $i + 1, $t), $competitorTitles, array_keys($competitorTitles)));

        // Shuffle a 20-word subset of the power-words registry into the
        // prompt — the model picks zero/one/two per rewrite as it sees
        // fit. Sending all ~60 would just bias toward stuffing; a smaller
        // rotation per call keeps each generation feeling fresh.
        $shuffled = self::POWER_WORDS;
        shuffle($shuffled);
        $powerWordSubset = array_slice($shuffled, 0, 20);
        $powerWordsBlock = implode(', ', $powerWordSubset);

        $additionalBlock = $additionalKeywords === []
            ? '(none provided)'
            : implode(', ', $additionalKeywords);

        $system = <<<'SYS'
You are a senior SEO copywriter. You produce snappy, click-worthy SEO titles
and meta descriptions that beat what's already ranking for the target query.

Universal constraints:
- Title: 50–60 characters. Lead with the focus keyword (or its close variant).
  No ALL CAPS, no lying, no generic clickbait.
- Meta description: 130–155 characters. Reinforce the focus keyword once,
  state the value the user gets, end with an implicit or explicit CTA.
- Lean on power words for CTR but never sacrifice clarity. At most one or
  two per title and one per meta — titles must read as real human-written
  headlines, not a clickbait stack.
- When additional keyphrases are provided, weave AT MOST ONE into ONE of the
  three rewrites where it fits naturally. Never force them, and never repeat
  the same additional keyphrase across multiple rewrites.
- Differentiate from the competitor titles when given — do not just rephrase.
- Return STRICTLY valid JSON with the schema below. No prose, no markdown.
SYS;

        // Two prompt modes:
        //   - AUTO  → 3 different angles from the full registry, model picks
        //   - INTENT → 3 distinct VARIATIONS of the same single intent
        if ($intent === self::INTENT_AUTO) {
            $intentList = implode(', ', array_keys(self::INTENTS));
            // Heredoc identifier MUST NOT collide with any token at the
            // start of an interior line. Earlier we used `MODE` and the
            // body contained `MODE:` — PHP closed the heredoc early and
            // parsed the rest as code. `EBQ_PROMPT_MODE_BLOCK` is unique.
            $modeBlock = <<<EBQ_PROMPT_MODE_BLOCK
MODE: auto-mix.
Pick THREE different angles from this registry:
  {$intentList}
Each rewrite uses a different angle. Set the "angle" field to the chosen
angle key from the list above.
EBQ_PROMPT_MODE_BLOCK;
        } else {
            $intentMeta = self::INTENTS[$intent];
            $intentLabel = $intentMeta['label'];
            $intentDesc = $intentMeta['desc'];
            $intentRule = $intentMeta['rule'];
            $modeBlock = <<<EBQ_PROMPT_MODE_BLOCK
MODE: single-intent variations.
Selected intent: "{$intent}" — {$intentLabel}.
What this angle is: {$intentDesc}
STRUCTURAL RULE for every rewrite (non-negotiable): {$intentRule}

Produce THREE distinct VARIATIONS of this same intent. Each variation
should differ from the others in at least two of: opening word, length
mix (shorter title vs longer meta vs vice versa), specificity (concrete
numbers / named examples), and the rationale focus. All three MUST set
"angle": "{$intent}".
EBQ_PROMPT_MODE_BLOCK;
        }

        $user = <<<USER
Focus keyword: "{$keyword}"

Additional keyphrases (weave AT MOST ONE into ONE of the three rewrites
where it fits — never forced, never across multiple rewrites): {$additionalBlock}

Power words you may draw on (zero, one, or two per title; one per meta;
never stuffed): {$powerWordsBlock}

Current SEO title: "{$currentTitle}"
Current meta description: "{$currentMeta}"

Top-ranking competitor titles for this query:
{$competitorBlock}

Content excerpt (use only for intent grounding — do not echo verbatim):
---
{$excerpt}
---

{$modeBlock}

Return JSON exactly in this shape:
{
  "rewrites": [
    {
      "angle": "<angle_key_from_registry>",
      "title": "...",
      "meta": "...",
      "rationale": "Why this rewrite works against the SERP, in one sentence."
    },
    ... (exactly 3 entries)
  ]
}
USER;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * @param  list<string>  $competitorTitles
     * @param  list<string>  $additionalKeywords
     */
    private function cacheKey(int $postId, string $keyword, string $title, string $meta, string $excerpt, array $competitorTitles, string $intent, array $additionalKeywords = []): string
    {
        $contentHash = hash('xxh3', $title . "\n" . $meta . "\n" . $excerpt);
        $compHash = hash('xxh3', implode('|', $competitorTitles));
        // Sort so the same set in different order returns the same cached result.
        $sortedAdditional = $additionalKeywords;
        sort($sortedAdditional, SORT_STRING);
        $additionalHash = hash('xxh3', implode('|', $sortedAdditional));
        return sprintf('ai_snippet_rewrite:%d:%s:%s:%s:%s:%s', $postId, hash('xxh3', $keyword), $contentHash, $compHash, $additionalHash, $intent);
    }
}
