<?php

namespace App\AiTools\Tools\Research;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class SeoDescription extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'seo-description',
            name: 'SEO Description Generator',
            category: Categories::RESEARCH,
            description: 'Meta description ≤155 chars, anchored to your top GSC queries.',
            inputs: [
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('summary', 'Post summary', 'textarea', required: true, maxLength: 1500),
                new InputField('url', 'Existing post URL (optional)', 'url'),
                new InputField('count', 'How many variants?', 'number', default: 3),
            ],
            outputType: 'list',
            estCredits: 3,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_SIDEBAR, AiTool::SURFACE_BULK],
            contextSignals: [AiTool::SIGNAL_GSC],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $kw = (string) ($input['focus_keyword'] ?? '');
        $summary = (string) ($input['summary'] ?? '');
        $count = max(1, min(5, (int) ($input['count'] ?? 3)));

        $gsc = '';
        if (is_array($context->gscTopQueries) && $context->gscTopQueries !== []) {
            $gsc = "\n\nGSC queries the page gets (work them in naturally where they fit):\n";
            foreach (array_slice($context->gscTopQueries, 0, 5) as $q) {
                $gsc .= "- {$q['query']}\n";
            }
        }

        return "Focus keyword: {$kw}\nSummary: {$summary}\n\n"
            . "Write {$count} meta-description variants.\n"
            . "HARD RULES (every variant must satisfy ALL):\n"
            . "  * Length 120 to 158 characters (sweet spot — under 120 Google pads the snippet from page body, over 158 it truncates).\n"
            . "  * The exact focus keyword '{$kw}' MUST appear verbatim in EVERY variant (or its obvious singular/plural form). No paraphrases.\n"
            . "  * Place the focus keyword in the first 100 characters so it shows even when truncated on small viewports.\n"
            . "  * End with a soft action verb (learn, discover, see, compare, find, get).\n"
            . "Output one variant per line, no numbering, no quotes."
            . $gsc;
    }
}
