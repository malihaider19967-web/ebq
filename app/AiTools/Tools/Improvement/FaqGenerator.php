<?php

namespace App\AiTools\Tools\Improvement;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class FaqGenerator extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'faq-generator',
            name: 'FAQ Generator',
            category: Categories::IMPROVEMENT,
            description: 'FAQ section grounded in real PAA questions and entities from the post.',
            inputs: [
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('article_text', 'Article body (optional)', 'textarea', maxLength: 16000),
                new InputField('count', 'How many questions?', 'number', default: 5),
            ],
            outputType: 'faq',
            estCredits: 6,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/group', 'core/list'],
            contextSignals: [AiTool::SIGNAL_RANK_SNAPSHOT, AiTool::SIGNAL_ENTITIES, AiTool::SIGNAL_SEO_ANALYSIS],
        );
    }

    protected function expectsJson(): bool
    {
        return true;
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $kw = (string) ($input['focus_keyword'] ?? '');
        $article = mb_substr((string) ($input['article_text'] ?? ''), 0, 12000);
        $count = max(3, min(10, (int) ($input['count'] ?? 5)));

        $paa = '';
        if (is_array($context->rankSnapshot) && ! empty($context->rankSnapshot['people_also_ask'])) {
            $paa = "\n\nLive PAA questions for this keyword (use as many as fit verbatim):\n";
            foreach (array_slice($context->rankSnapshot['people_also_ask'], 0, 6) as $q) {
                if (is_string($q)) {
                    $paa .= "- {$q}\n";
                }
            }
        }

        $body = $article !== '' ? "\n\nArticle body for grounding:\n{$article}" : '';

        return "Focus keyword: {$kw}\n"
            . "Generate {$count} FAQ entries. Return ONE JSON array:\n"
            . "[ { \"question\": string, \"answer\": string }, ... ]\n"
            . "Each answer 30–80 words, conversational, snippet-friendly. The answer must be findable / inferrable from the article body when supplied."
            . $paa . $body;
    }
}
