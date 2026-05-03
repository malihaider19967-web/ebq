<?php

namespace App\AiTools\Tools\Research;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class SeoMeta extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'seo-meta',
            name: 'SEO Title + Description + OG',
            category: Categories::RESEARCH,
            description: 'One pass — generates SEO title, meta description, OpenGraph title, and OpenGraph description together.',
            inputs: [
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('summary', 'Post summary', 'textarea', required: true, maxLength: 2000),
                new InputField('url', 'Existing post URL (optional)', 'url'),
            ],
            outputType: 'json',
            estCredits: 6,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_SIDEBAR],
            contextSignals: [AiTool::SIGNAL_GSC],
        );
    }

    protected function expectsJson(): bool
    {
        return true;
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $kw = (string) ($input['focus_keyword'] ?? '');
        $summary = (string) ($input['summary'] ?? '');

        $gsc = '';
        if (is_array($context->gscTopQueries) && $context->gscTopQueries !== []) {
            $gsc = "\n\nQueries the page gets:\n";
            foreach (array_slice($context->gscTopQueries, 0, 6) as $q) {
                $gsc .= "- {$q['query']}\n";
            }
        }

        return "Focus keyword: {$kw}\nSummary: {$summary}\n\n"
            . "Return ONE JSON object:\n"
            . "{\n"
            . "  \"seo_title\": string (50 to 60 chars, full SERP width sweet spot),\n"
            . "  \"seo_description\": string (120 to 158 chars sweet spot, under 120 Google pads the snippet, over 158 it truncates),\n"
            . "  \"og_title\": string (≤70 chars, more conversational than seo_title),\n"
            . "  \"og_description\": string (≤180 chars, social-share-friendly)\n"
            . "}\n"
            . "HARD RULES:\n"
            . "  * The exact focus keyword '{$kw}' MUST appear verbatim in BOTH seo_title AND seo_description (or its obvious singular/plural form). No paraphrases.\n"
            . "  * Place the focus keyword in the first half of seo_title and the first 100 chars of seo_description.\n"
            . "  * og_* should feel like a friend recommending the post. Focus keyword encouraged there, not required."
            . $gsc;
    }
}
