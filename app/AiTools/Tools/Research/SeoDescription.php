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
            . "Write {$count} meta-description variants. Each MUST be ≤155 characters. Each contains the focus keyword naturally. End with a soft action verb (learn, discover, see, compare). Output one per line, no numbering, no quotes."
            . $gsc;
    }
}
