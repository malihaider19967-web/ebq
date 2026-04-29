<?php

namespace App\Services;

use App\Models\Website;
use App\Services\Llm\LlmClient;

/**
 * Rank Assist — SEO-helper chatbot for the WordPress plugin's floating
 * assistant.
 *
 * Multi-turn conversation grounded in the post being edited. The system
 * prompt locks the assistant to SEO/content topics and structures every
 * reply as JSON: { reply: string, action: null|object }.
 *
 * When the user asks for a fix (rewrite my meta description, shorten my
 * title, change focus keyword, etc.) the model emits an `action` proposal
 * — the WordPress side renders it with Apply / Discard buttons so the
 * change is never silent. The reply text restates what the model
 * understood, cites the current value, names the proposed value, and
 * explains the rationale before any change lands.
 */
class AiChatService
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    public const ACTION_POST_TITLE = 'update_post_title';
    public const ACTION_FOCUS_KEYWORD = 'update_focus_keyword';
    public const ACTION_ADDITIONAL_KEYWORDS = 'update_additional_keywords';
    public const ACTION_META_TITLE = 'update_meta_title';
    public const ACTION_META_DESCRIPTION = 'update_meta_description';

    public const ACTION_TYPES = [
        self::ACTION_POST_TITLE,
        self::ACTION_FOCUS_KEYWORD,
        self::ACTION_ADDITIONAL_KEYWORDS,
        self::ACTION_META_TITLE,
        self::ACTION_META_DESCRIPTION,
    ];

    private const ACTION_LIMITS = [
        self::ACTION_POST_TITLE => 200,
        self::ACTION_FOCUS_KEYWORD => 100,
        self::ACTION_META_TITLE => 200,
        self::ACTION_META_DESCRIPTION => 320,
    ];

    private const MAX_HISTORY = 20;
    private const MAX_MESSAGE_CHARS = 4000;
    private const MAX_CONTENT_EXCERPT = 4000;

    public function __construct(
        private readonly LlmClient $llm,
    ) {
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $context
     * @return array{ok: bool, reply?: string, action?: array<string, mixed>|null, error?: string}
     */
    public function chat(Website $website, array $messages, array $context = []): array
    {
        if (! $this->llm->isAvailable()) {
            return ['ok' => false, 'error' => 'llm_not_configured'];
        }

        $history = $this->sanitiseHistory($messages);
        if (empty($history)) {
            return ['ok' => false, 'error' => 'missing_messages'];
        }
        if (end($history)['role'] !== self::ROLE_USER) {
            return ['ok' => false, 'error' => 'last_message_must_be_user'];
        }

        $payload = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt()]],
            [['role' => 'system', 'content' => $this->contextPrompt($website, $context)]],
            $history,
        );

        $decoded = $this->llm->completeJson($payload, [
            'temperature' => 0.3,
            'max_tokens' => 900,
            'timeout' => 60,
        ]);

        if ($decoded === null) {
            return ['ok' => false, 'error' => 'llm_failed'];
        }

        $reply = trim((string) ($decoded['reply'] ?? ''));
        if ($reply === '') {
            return ['ok' => false, 'error' => 'llm_empty_response'];
        }

        $action = $this->validateAction($decoded['action'] ?? null);

        return [
            'ok' => true,
            'reply' => $reply,
            'action' => $action,
        ];
    }

    /**
     * @param  list<array{role?: string, content?: string}>  $messages
     * @return list<array{role: string, content: string}>
     */
    private function sanitiseHistory(array $messages): array
    {
        $allowed = [self::ROLE_USER, self::ROLE_ASSISTANT];
        $clean = [];
        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? '');
            $content = trim((string) ($m['content'] ?? ''));
            if (! in_array($role, $allowed, true) || $content === '') {
                continue;
            }
            if (mb_strlen($content) > self::MAX_MESSAGE_CHARS) {
                $content = mb_substr($content, 0, self::MAX_MESSAGE_CHARS);
            }
            $clean[] = ['role' => $role, 'content' => $content];
        }
        if (count($clean) > self::MAX_HISTORY) {
            $clean = array_slice($clean, -self::MAX_HISTORY);
        }
        return $clean;
    }

    /**
     * Strict validation of any action the model proposes. Drop on shape or
     * size violations — better silent dismissal than letting a malformed
     * action render in the UI.
     *
     * @return array<string, mixed>|null
     */
    private function validateAction(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $type = (string) ($raw['type'] ?? '');
        if (! in_array($type, self::ACTION_TYPES, true)) {
            return null;
        }

        $summary = trim((string) ($raw['summary'] ?? ''));
        if (mb_strlen($summary) > 200) {
            $summary = mb_substr($summary, 0, 200);
        }

        if ($type === self::ACTION_ADDITIONAL_KEYWORDS) {
            $value = $raw['value'] ?? null;
            if (! is_array($value)) {
                return null;
            }
            $clean = [];
            foreach ($value as $k) {
                $s = trim((string) $k);
                if ($s === '') continue;
                if (mb_strlen($s) > 100) {
                    $s = mb_substr($s, 0, 100);
                }
                $clean[] = $s;
                if (count($clean) >= 5) break;
            }
            if (empty($clean)) {
                return null;
            }
            return [
                'type' => $type,
                'value' => $clean,
                'summary' => $summary !== '' ? $summary : 'Update additional keyphrases',
            ];
        }

        $value = trim((string) ($raw['value'] ?? ''));
        if ($value === '') {
            return null;
        }
        $limit = self::ACTION_LIMITS[$type] ?? 1000;
        if (mb_strlen($value) > $limit) {
            return null;
        }

        return [
            'type' => $type,
            'value' => $value,
            'summary' => $summary !== '' ? $summary : $this->defaultSummary($type),
        ];
    }

    private function defaultSummary(string $type): string
    {
        return match ($type) {
            self::ACTION_POST_TITLE => 'Update post title',
            self::ACTION_FOCUS_KEYWORD => 'Update focus keyword',
            self::ACTION_META_TITLE => 'Update SEO meta title',
            self::ACTION_META_DESCRIPTION => 'Update SEO meta description',
            default => 'Apply update',
        };
    }

    private function systemPrompt(): string
    {
        $actions = "- update_post_title (value: string, ≤200 chars)\n"
            ."- update_focus_keyword (value: string, ≤100 chars)\n"
            ."- update_additional_keywords (value: array of strings, max 5 items)\n"
            ."- update_meta_title (value: string, ≤200 chars; SEO display sweet spot 50–60)\n"
            ."- update_meta_description (value: string, ≤320 chars; SEO display sweet spot 150–160)";

        return "You are Rank Assist, a focused SEO and content-quality helper embedded in the WordPress post editor. Your ONLY job is to help the user improve the SEO, search ranking potential, readability, structure, and editorial quality of the post they are currently editing.\n\n"
            ."OUTPUT FORMAT — every reply is strict JSON with this exact shape:\n"
            ."{\n"
            ."  \"reply\": \"<message shown to the user>\",\n"
            ."  \"action\": null OR { \"type\": \"<one of the action types>\", \"value\": <value>, \"summary\": \"<one-line label for the Apply button>\" }\n"
            ."}\n"
            ."Allowed action types and value shapes:\n{$actions}\n"
            ."If you are NOT proposing a concrete edit, set action to null.\n\n"
            ."WHEN TO PROPOSE AN ACTION:\n"
            ."- The user explicitly asks you to fix, rewrite, change, update, set, or apply something the actions above can do (the title, focus keyword, additional keywords, meta title, or meta description).\n"
            ."- You have enough context to write the new value yourself. If you don't, ask a clarifying question instead (action = null).\n"
            ."- Only propose ONE action per turn. If multiple things need fixing, pick the highest-impact one and tell the user you can do the rest in follow-up turns.\n"
            ."- Never propose an action for content the user has not asked to change.\n\n"
            ."WHEN YOU PROPOSE AN ACTION, the `reply` text MUST:\n"
            ."1. Restate what you understood from the user's message in one sentence (\"You'd like me to rewrite the meta description.\").\n"
            ."2. Cite the current value verbatim (or note 'currently empty') with its character count when relevant.\n"
            ."3. State the proposed new value (or a quoted preview) and its character count.\n"
            ."4. Explain in one short sentence WHY this change helps SEO (length, keyword placement, intent fit, clarity, etc.).\n"
            ."5. Make it clear the change is NOT yet applied — phrase it as a proposal awaiting the user's confirmation. Never say 'I've updated' or 'I changed'.\n"
            ."The Apply button shows your `summary` text — keep it tight, e.g. 'Replace meta description (42 → 158 chars)' or 'Set focus keyword to \"thai green curry recipe\"'.\n\n"
            ."WHEN YOU DON'T PROPOSE AN ACTION:\n"
            ."- Answer the user's question directly using the post context provided. Concrete, post-specific, no fluff.\n"
            ."- If the user asks for advice you could *propose as an action* but you need clarification (e.g. 'fix my title' but you don't know which direction), ask one focused clarifying question.\n\n"
            ."SCOPE — what you DO:\n"
            ."- Analyse focus keyword usage, semantic variants, search intent fit.\n"
            ."- Critique title, meta description, headings, internal/external linking, schema, canonical, slug.\n"
            ."- Suggest improvements to readability, structure, depth of coverage, E-E-A-T signals.\n"
            ."- Interpret offline checks (length, keyword presence, heading hierarchy) and live audit data (Core Web Vitals, GSC performance, audit score) supplied in the context.\n\n"
            ."SCOPE — what you DECLINE:\n"
            ."- Off-topic chat (general coding help, personal advice, jokes, news, anything unrelated to this post's SEO).\n"
            ."- Generating full long-form drafts (point users to the AI Writer feature instead).\n"
            ."- Politics, medical/legal/financial advice unrelated to SEO copy review.\n"
            ."When asked anything off-topic, briefly say you're an SEO helper for this post and steer back to the post's SEO — do not lecture, set action to null.\n\n"
            ."STYLE:\n"
            ."- Concise. The reply field should be 2–6 short sentences. Don't pad.\n"
            ."- Reference the actual post data you were given (title, focus keyword, audit numbers) — do not invent facts.\n"
            ."- If a relevant signal is missing from the context (e.g. no focus keyword set), say so and ask the user to set it (or propose an action to set one if they've hinted at the keyword).\n"
            ."- Plain text or simple Markdown lists in `reply`. No HTML tags, no code fences.\n\n"
            ."Always emit valid JSON. Never include any text outside the JSON object.";
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextPrompt(Website $website, array $context): string
    {
        $lines = [];
        $lines[] = 'CURRENT POST CONTEXT (from the WordPress editor — refer to this when answering):';

        $site = trim((string) ($website->domain ?? ''));
        if ($site !== '') {
            $lines[] = "- Site: {$site}";
        }

        $title = trim((string) ($context['title'] ?? ''));
        if ($title !== '') {
            $lines[] = '- Live editor title: '.$title.' ('.mb_strlen($title).' chars)';
        }

        $url = trim((string) ($context['url'] ?? ''));
        if ($url !== '') {
            $lines[] = "- URL: {$url}";
        }

        $postType = trim((string) ($context['post_type'] ?? ''));
        if ($postType !== '') {
            $lines[] = "- Post type: {$postType}";
        }

        $focus = trim((string) ($context['focus_keyword'] ?? ''));
        if ($focus !== '') {
            $lines[] = "- Focus keyword: {$focus}";
        } else {
            $lines[] = '- Focus keyword: NOT SET (recommend the user set one — you may propose update_focus_keyword if they hint at the topic)';
        }

        $additional = $context['additional_keywords'] ?? [];
        if (is_array($additional) && ! empty($additional)) {
            $clean = array_values(array_filter(array_map(static fn ($k) => trim((string) $k), $additional), static fn ($s) => $s !== ''));
            if (! empty($clean)) {
                $lines[] = '- Additional keyphrases: '.implode(', ', array_slice($clean, 0, 10));
            }
        }

        $metaTitle = trim((string) ($context['meta_title'] ?? ''));
        if ($metaTitle !== '') {
            $lines[] = "- SEO meta title: {$metaTitle} (".mb_strlen($metaTitle).' chars)';
        } else {
            $lines[] = '- SEO meta title: NOT SET';
        }

        $metaDescription = trim((string) ($context['meta_description'] ?? ''));
        if ($metaDescription !== '') {
            $lines[] = "- SEO meta description: {$metaDescription} (".mb_strlen($metaDescription).' chars)';
        } else {
            $lines[] = '- SEO meta description: NOT SET';
        }

        $excerpt = trim((string) ($context['content_excerpt'] ?? ''));
        if ($excerpt !== '') {
            if (mb_strlen($excerpt) > self::MAX_CONTENT_EXCERPT) {
                $excerpt = mb_substr($excerpt, 0, self::MAX_CONTENT_EXCERPT).'…';
            }
            $lines[] = "- Live editor content (truncated):\n{$excerpt}";
        }

        $offline = $context['offline_audit'] ?? null;
        if (is_array($offline) && ! empty($offline)) {
            $lines[] = '- Offline checks (computed client-side from current editor state):';
            foreach ($offline as $key => $value) {
                $k = is_string($key) ? $key : (string) $key;
                $v = $this->scalarise($value);
                if ($v !== '') {
                    $lines[] = "  · {$k}: {$v}";
                }
            }
        }

        $live = $context['live_audit'] ?? null;
        if (is_array($live) && ! empty($live)) {
            $lines[] = '- Live audit (from EBQ backend — GSC + Lighthouse + on-page audit):';
            foreach ($live as $key => $value) {
                $k = is_string($key) ? $key : (string) $key;
                $v = $this->scalarise($value);
                if ($v !== '') {
                    $lines[] = "  · {$k}: {$v}";
                }
            }
        }

        $lines[] = '';
        $lines[] = 'When the user asks about "this post", "the title", "the content", etc., use the data above. Do not invent values that are not listed. Remember: any concrete edit must be proposed via the action field — never describe the change as already applied.';

        return implode("\n", $lines);
    }

    private function scalarise(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $k => $v) {
                if (is_scalar($v)) {
                    $parts[] = is_int($k) ? (string) $v : "{$k}={$v}";
                }
            }
            return implode(', ', array_slice($parts, 0, 12));
        }
        return '';
    }
}
