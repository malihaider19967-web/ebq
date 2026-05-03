<?php

namespace App\AiTools\Tools\Research;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class RelatedSearches extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'related-searches',
            name: 'Related Searches',
            category: Categories::RESEARCH,
            description: '"People also search for" queries — adjacent topics worth considering.',
            inputs: [
                new InputField('keyword', 'Keyword', 'text', required: true, maxLength: 200),
            ],
            outputType: 'list',
            estCredits: 3,
            surfaces: [AiTool::SURFACE_STUDIO],
            contextSignals: [AiTool::SIGNAL_RANK_SNAPSHOT],
            cacheTtlSeconds: 3600,
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $keyword = (string) ($input['keyword'] ?? '');

        $known = '';
        if (is_array($context->rankSnapshot) && ! empty($context->rankSnapshot['related_searches'])) {
            $known = "\n\nThese related searches are live on the SERP (include them):\n";
            foreach (array_slice($context->rankSnapshot['related_searches'], 0, 8) as $q) {
                if (is_string($q)) {
                    $known .= "- {$q}\n";
                }
            }
        }

        return "List 10 related searches for: {$keyword}. One per line, no numbering."
            . $known;
    }
}
