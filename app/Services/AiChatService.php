<?php

namespace App\Services;

use App\Models\Website;
use App\Services\Llm\LlmClient;

/**
 * SEO-helper chatbot for the WordPress plugin's floating assistant.
 *
 * Multi-turn conversation grounded in the post being edited. The system
 * prompt locks the assistant to SEO/content topics — it politely declines
 * anything else. Context (live title/content, EBQ meta, offline + live
 * audit signals) is supplied per-request by the caller and folded into a
 * single hidden system message so it does not pollute the visible turn
 * history.
 */
class AiChatService
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

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
     * @return array{ok: bool, reply?: string, error?: string}
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

        $response = $this->llm->complete($payload, [
            'temperature' => 0.4,
            'max_tokens' => 900,
            'timeout' => 60,
        ]);

        if (! ($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($response['error'] ?? 'llm_failed')];
        }

        $reply = trim((string) ($response['content'] ?? ''));
        if ($reply === '') {
            return ['ok' => false, 'error' => 'llm_empty_response'];
        }

        return ['ok' => true, 'reply' => $reply];
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

    private function systemPrompt(): string
    {
        return "You are EBQ Assistant, a focused SEO and content-quality helper embedded in the WordPress post editor. Your ONLY job is to help the user improve the SEO, search ranking potential, readability, structure, and editorial quality of the post they are currently editing.\n\n"
            ."SCOPE — what you DO:\n"
            ."- Analyse focus keyword usage, semantic variants, search intent fit.\n"
            ."- Critique title, meta description, headings, internal/external linking, schema, canonical, slug.\n"
            ."- Suggest improvements to readability, structure, depth of coverage, E-E-A-T signals.\n"
            ."- Interpret offline checks (length, keyword presence, heading hierarchy) and live audit data (Core Web Vitals, GSC performance, audit score) supplied in the context.\n"
            ."- Recommend concrete, testable edits — never vague advice like 'make it more engaging'.\n\n"
            ."SCOPE — what you DECLINE:\n"
            ."- Off-topic chat (general coding help, personal advice, jokes, news, anything unrelated to this post's SEO).\n"
            ."- Generating full long-form drafts (point users to the AI Writer feature instead).\n"
            ."- Politics, medical/legal/financial advice unrelated to SEO copy review.\n"
            ."When asked anything off-topic, briefly say you're an SEO helper for this post and steer back to the post's SEO — do not lecture.\n\n"
            ."STYLE:\n"
            ."- Concise. Default to 2–6 short sentences or a tight bulleted list. Don't pad.\n"
            ."- Reference the actual post data you were given (title, focus keyword, audit numbers) — do not invent facts.\n"
            ."- If a relevant signal is missing from the context (e.g. no focus keyword set), say so and ask the user to set it.\n"
            ."- Plain text or simple Markdown lists. No HTML tags, no code fences unless quoting code.";
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
            $lines[] = "- Live editor title: {$title}";
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
            $lines[] = '- Focus keyword: NOT SET (recommend the user set one in the EBQ sidebar)';
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
        }

        $metaDescription = trim((string) ($context['meta_description'] ?? ''));
        if ($metaDescription !== '') {
            $lines[] = "- SEO meta description: {$metaDescription} (".mb_strlen($metaDescription).' chars)';
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
        $lines[] = 'When the user asks about "this post", "the title", "the content", etc., use the data above. Do not invent values that are not listed.';

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
