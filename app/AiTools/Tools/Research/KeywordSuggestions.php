<?php

namespace App\AiTools\Tools\Research;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class KeywordSuggestions extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'keyword-suggestions',
            name: 'Keyword Suggestions',
            category: Categories::RESEARCH,
            description: 'Semantically related keywords ranked by your unranked-GSC opportunity.',
            inputs: [
                new InputField('keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
            ],
            outputType: 'list',
            estCredits: 5,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_SIDEBAR],
            contextSignals: [AiTool::SIGNAL_GSC],
            cacheTtlSeconds: 3600,
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $keyword = (string) ($input['keyword'] ?? '');

        $gsc = '';
        if (is_array($context->gscClustersForKeyword) && ! empty($context->gscClustersForKeyword['related_queries'])) {
            $unranked = array_filter($context->gscClustersForKeyword['related_queries'], static fn ($q) => $q['position'] > 10);
            if ($unranked !== []) {
                $gsc = "\n\nQueries the site already gets impressions for but ranks past page 1 (PRIORITISE — these are striking-distance):\n";
                foreach (array_slice($unranked, 0, 10) as $q) {
                    $gsc .= "- {$q['query']} (pos: {$q['position']})\n";
                }
            }
        }

        return "Suggest 15 keyword variations and semantically-related queries for: {$keyword}.\n"
            . "Return one per line, no numbering or bullets, just the keyword text. Skip duplicates of the focus keyword."
            . $gsc;
    }
}
