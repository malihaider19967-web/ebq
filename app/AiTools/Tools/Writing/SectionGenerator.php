<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class SectionGenerator extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'section-generator',
            name: 'Section Generator',
            category: Categories::WRITING,
            description: 'One H2 + body for a topic, optionally with internal-link suggestions woven in.',
            inputs: [
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('h2', 'Section heading', 'text', required: true, maxLength: 200),
                new InputField('words', 'Target word count', 'number', default: 250),
            ],
            outputType: 'html',
            estCredits: 12,
            surfaces: [AiTool::SURFACE_STUDIO],
            contextSignals: [AiTool::SIGNAL_BRIEF, AiTool::SIGNAL_INTERNAL_LINKS, AiTool::SIGNAL_SEO_ANALYSIS],
            cacheTtlSeconds: 1800,
        );
    }

    protected function llmOptions(): array
    {
        return ['temperature' => 0.55, 'max_tokens' => 2200, 'timeout' => 90];
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $kw = (string) ($input['focus_keyword'] ?? '');
        $h2 = (string) ($input['h2'] ?? '');
        $words = max(80, min(900, (int) ($input['words'] ?? 250)));

        $links = '';
        if (is_array($context->internalLinkCandidates) && $context->internalLinkCandidates !== []) {
            $links = "\n\nInternal-link candidates (pick 0–2 that fit naturally; each link MUST use the URL verbatim and an anchor that reads naturally):\n";
            foreach (array_slice($context->internalLinkCandidates, 0, 6) as $c) {
                $links .= "- {$c['url']} (best for: {$c['topic']})\n";
            }
        }

        $brief = '';
        if (is_array($context->cachedBrief)) {
            $sub = (array) ($context->cachedBrief['subtopics'] ?? []);
            if ($sub !== []) {
                $brief = "\n\nBrief subtopics (cover what fits):\n- " . implode("\n- ", array_slice($sub, 0, 8));
            }
        }

        return "Focus keyword: {$kw}\nSection heading: {$h2}\nTarget word count: ~{$words}\n\n"
            . "Write ONE section in HTML. Start with <h2>{$h2}</h2>. Follow with paragraphs and at most one short list. No <h1>. No images. The focus keyword should appear once or twice naturally — never stuffed."
            . $brief . $links;
    }
}
