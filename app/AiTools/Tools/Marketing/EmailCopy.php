<?php

namespace App\AiTools\Tools\Marketing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class EmailCopy extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'email-copy',
            name: 'Email Copy',
            category: Categories::MARKETING,
            description: 'Newsletter or outreach email — subject line + body — in your brand voice.',
            inputs: [
                new InputField('email_type', 'Email type', 'select', options: [
                    ['value' => 'newsletter', 'label' => 'Newsletter (broadcast)'],
                    ['value' => 'outreach', 'label' => 'Cold outreach'],
                    ['value' => 'announcement', 'label' => 'Announcement'],
                    ['value' => 'nurture', 'label' => 'Nurture sequence email'],
                ], default: 'newsletter'),
                new InputField('topic', 'Topic / offer', 'textarea', required: true, maxLength: 2000),
                new InputField('cta', 'Call-to-action', 'text', required: true, maxLength: 200),
                new InputField('cta_url', 'CTA URL (optional)', 'url'),
            ],
            outputType: 'json',
            estCredits: 6,
            surfaces: [AiTool::SURFACE_STUDIO],
        );
    }

    protected function expectsJson(): bool
    {
        return true;
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $type = (string) ($input['email_type'] ?? 'newsletter');
        $topic = (string) ($input['topic'] ?? '');
        $cta = (string) ($input['cta'] ?? '');
        $url = (string) ($input['cta_url'] ?? '');

        $rules = match ($type) {
            'outreach' => '120–180 words. First line is personalised — no "Hope this finds you well". One ask, no pitch deck.',
            'announcement' => '100–150 words. Lead with the news; explain why it matters to the reader.',
            'nurture' => '150–220 words. Lesson-led, one teachable insight, soft CTA.',
            default => '180–260 words. Conversational. One main idea + 1–2 supporting bullets if helpful.',
        };

        $linkLine = $url !== '' ? "Include this URL verbatim where the CTA goes: {$url}" : '';

        return "Email type: {$type}\nTopic: {$topic}\nCTA: {$cta}\n{$linkLine}\n\n"
            . "Return ONE JSON object: { \"subject_lines\": string[3], \"preview_text\": string (≤90 chars), \"body\": string }.\n"
            . "Body rules: {$rules} Plain text body — no HTML.";
    }
}
