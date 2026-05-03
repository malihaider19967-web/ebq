<?php

namespace App\AiTools\Tools\Research;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class SeoTitle extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'seo-title',
            name: 'SEO Title Generator',
            category: Categories::RESEARCH,
            description: '5 title variants tuned to your top GSC queries and the live SERP.',
            inputs: [
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('summary', 'What\'s the post about?', 'textarea',
                    placeholder: 'One paragraph the model can use to anchor the title.', maxLength: 1500),
                new InputField('url', 'Existing post URL (optional)', 'url',
                    help: 'Used to pull the post\'s top GSC queries.'),
            ],
            outputType: 'titles',
            estCredits: 4,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_SIDEBAR, AiTool::SURFACE_BULK],
            contextSignals: [AiTool::SIGNAL_GSC, AiTool::SIGNAL_RANK_SNAPSHOT],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $kw = (string) ($input['focus_keyword'] ?? '');
        $summary = (string) ($input['summary'] ?? '');

        $gsc = '';
        if (is_array($context->gscTopQueries) && $context->gscTopQueries !== []) {
            $gsc = "\n\nThe page already gets these queries from Google (lean into them when natural):\n";
            foreach (array_slice($context->gscTopQueries, 0, 6) as $q) {
                $gsc .= "- {$q['query']} (clicks: {$q['clicks']})\n";
            }
        }
        $serp = '';
        if (is_array($context->rankSnapshot) && ! empty($context->rankSnapshot['top_results'])) {
            $serp = "\n\nLive SERP top titles for this keyword (differentiate from these):\n";
            foreach (array_slice($context->rankSnapshot['top_results'], 0, 5) as $r) {
                if (is_array($r) && is_string($r['title'] ?? null)) {
                    $serp .= "- {$r['title']}\n";
                }
            }
        }

        return "Focus keyword: {$kw}\n"
            . ($summary !== '' ? "Summary: {$summary}\n" : '')
            . "Generate exactly 8 SEO title variants (over-generate so the picker has plenty after server-side filtering).\n"
            . "HARD RULES (every title must satisfy ALL):\n"
            . "  * Length 50 to 60 characters strictly. Count the characters before writing each title. Under 50 wastes SERP space, over 60 gets truncated.\n"
            . "  * The exact focus keyword '{$kw}' MUST appear verbatim in EVERY title (or its obvious singular/plural form when grammar requires). No paraphrases, no synonyms.\n"
            . "  * Place the focus keyword in the first half of the title.\n"
            . "Mix angle across the 8: how-to, listicle, definitive, comparison, contrarian, year-stamped, question-led, benefit-led. Output one title per line, no numbering, no quotes, no trailing period."
            . $gsc . $serp;
    }
}
