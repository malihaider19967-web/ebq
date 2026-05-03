<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ConclusionGenerator extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'conclusion-generator',
            name: 'Conclusion Generator',
            category: Categories::WRITING,
            description: 'Conclusion paragraph with a soft, on-brand call-to-action.',
            inputs: [
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('article_text', 'Existing article (for context)', 'textarea',
                    required: true, maxLength: 12000),
                new InputField('cta_url', 'CTA destination URL (optional)', 'url'),
            ],
            outputType: 'text',
            estCredits: 4,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph'],
            contextSignals: [AiTool::SIGNAL_INTERNAL_LINKS],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $kw = (string) ($input['focus_keyword'] ?? '');
        $body = mb_substr((string) ($input['article_text'] ?? ''), 0, 12000);
        $cta = (string) ($input['cta_url'] ?? '');

        $linkBlock = '';
        if ($cta !== '') {
            $linkBlock = "\nLink the soft CTA to: {$cta}";
        }

        return "Focus keyword: {$kw}\nArticle body:\n{$body}\n\n"
            . "Write a conclusion paragraph of 60–110 words. Reinforce the article's key takeaway. End with a soft, useful call-to-action — never aggressive sales language."
            . $linkBlock;
    }
}
