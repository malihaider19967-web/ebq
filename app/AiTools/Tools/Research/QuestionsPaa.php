<?php

namespace App\AiTools\Tools\Research;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class QuestionsPaa extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'questions-paa',
            name: 'Questions (People Also Ask)',
            category: Categories::RESEARCH,
            description: 'PAA questions for a keyword, sourced from live SERP and rank-tracking history.',
            inputs: [
                new InputField('keyword', 'Keyword', 'text', required: true, maxLength: 200),
            ],
            outputType: 'list',
            estCredits: 4,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_SIDEBAR],
            contextSignals: [AiTool::SIGNAL_RANK_SNAPSHOT],
            cacheTtlSeconds: 3600,
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $keyword = (string) ($input['keyword'] ?? '');

        $known = '';
        if (is_array($context->rankSnapshot) && ! empty($context->rankSnapshot['people_also_ask'])) {
            $known = "\n\nThese PAA questions are confirmed live on the SERP for this keyword (use them verbatim):\n";
            foreach (array_slice($context->rankSnapshot['people_also_ask'], 0, 8) as $q) {
                if (is_string($q)) {
                    $known .= "- {$q}\n";
                } elseif (is_array($q) && is_string($q['question'] ?? null)) {
                    $known .= "- {$q['question']}\n";
                }
            }
        }

        return "List 10 'People Also Ask' style questions for the keyword: {$keyword}.\n"
            . "Each question should be conversational and direct. One per line, no numbering."
            . $known;
    }
}
