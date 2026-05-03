<?php

namespace App\AiTools\Tools\Research;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class KeywordResearch extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'keyword-research',
            name: 'Keyword Research',
            category: Categories::RESEARCH,
            description: 'Long-tail keyword candidates with intent labels, prioritised against your GSC profile.',
            inputs: [
                new InputField('seed_keyword', 'Seed keyword', 'text', required: true,
                    placeholder: 'e.g. ergonomic office chair', maxLength: 200),
                new InputField('count', 'How many ideas?', 'number', default: 20),
            ],
            outputType: 'table',
            estCredits: 10,
            surfaces: [AiTool::SURFACE_STUDIO],
            contextSignals: [AiTool::SIGNAL_GSC],
            cacheTtlSeconds: 3600,
        );
    }

    protected function expectsJson(): bool
    {
        return true;
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $seed = (string) ($input['seed_keyword'] ?? '');
        $count = max(5, min(50, (int) ($input['count'] ?? 20)));

        $gsc = '';
        if (is_array($context->gscClustersForKeyword) && ! empty($context->gscClustersForKeyword['related_queries'])) {
            $gsc = "\n\nQueries the user's site already gets impressions for (consider gaps):\n";
            foreach (array_slice($context->gscClustersForKeyword['related_queries'], 0, 10) as $q) {
                $gsc .= "- {$q['query']} (impr: {$q['impressions']}, pos: {$q['position']})\n";
            }
        }

        return "Seed: {$seed}\nReturn a JSON object with this exact shape:\n"
            . "{\n  \"headers\": [\"keyword\", \"intent\", \"priority\"],\n"
            . "  \"rows\": [[string, string, string], ...]\n}\n"
            . "Generate {$count} keyword variations. `intent` is one of: informational, commercial, transactional, navigational. `priority` is one of: high, medium, low — high when the keyword has clear commercial value AND the site doesn't already rank for it."
            . $gsc;
    }
}
