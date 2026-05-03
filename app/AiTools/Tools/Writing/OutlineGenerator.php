<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class OutlineGenerator extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'outline-generator',
            name: 'Outline Generator',
            category: Categories::WRITING,
            description: 'H1 + H2 outline shaped by your cached brief and PAA.',
            inputs: [
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('title', 'Working title (optional)', 'text', maxLength: 300),
            ],
            outputType: 'json',
            estCredits: 6,
            surfaces: [AiTool::SURFACE_STUDIO],
            contextSignals: [AiTool::SIGNAL_BRIEF, AiTool::SIGNAL_RANK_SNAPSHOT, AiTool::SIGNAL_NETWORK_INSIGHT],
            cacheTtlSeconds: 3600,
        );
    }

    protected function expectsJson(): bool
    {
        return true;
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $kw = (string) ($input['focus_keyword'] ?? '');
        $title = (string) ($input['title'] ?? '');

        $brief = '';
        if (is_array($context->cachedBrief) && ! empty($context->cachedBrief['suggested_outline'])) {
            $brief = "\n\nCached brief outline (incorporate / improve):\n";
            foreach (array_slice((array) $context->cachedBrief['suggested_outline'], 0, 12) as $h) {
                if (is_string($h)) {
                    $brief .= "- {$h}\n";
                }
            }
        }

        $networkHeadings = '';
        if (is_array($context->networkInsight) && ($context->networkInsight['typical_headings'] ?? null)) {
            $networkHeadings = "\nTop pages for this kw average " . (int) $context->networkInsight['typical_headings'] . " headings — match that range.";
        }

        return "Focus keyword: {$kw}\n"
            . ($title !== '' ? "Working title: {$title}\n" : '')
            . "Return ONE JSON object:\n{\n  \"h1\": string,\n  \"sections\": [{ \"h2\": string, \"subtopics\": string[] }, ...]\n}\n"
            . "Aim for 6–10 sections. H1 must include the focus keyword naturally. Each H2 should be search-friendly (mirror common phrasings users type)."
            . $brief . $networkHeadings;
    }
}
