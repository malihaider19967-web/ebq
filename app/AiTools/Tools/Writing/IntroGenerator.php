<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class IntroGenerator extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'intro-generator',
            name: 'Introduction Generator',
            category: Categories::WRITING,
            description: 'Article intro (40–80 words) that hooks the reader and sets the focus.',
            inputs: [
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('summary', 'What\'s the article about?', 'textarea', required: true, maxLength: 1500),
                new InputField('hook_style', 'Hook style', 'select', options: [
                    ['value' => 'question', 'label' => 'Question'],
                    ['value' => 'stat', 'label' => 'Surprising stat'],
                    ['value' => 'anecdote', 'label' => 'Mini anecdote'],
                    ['value' => 'contrarian', 'label' => 'Contrarian take'],
                    ['value' => 'direct', 'label' => 'Direct promise'],
                ], default: 'direct'),
            ],
            outputType: 'text',
            estCredits: 3,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph'],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $kw = (string) ($input['focus_keyword'] ?? '');
        $summary = (string) ($input['summary'] ?? '');
        $hook = (string) ($input['hook_style'] ?? 'direct');

        return "Focus keyword: {$kw}\nArticle summary: {$summary}\nHook style: {$hook}\n\n"
            . "Write an article introduction of 40–80 words. Open with the hook style above. The focus keyword appears once, naturally. The intro must end by signalling exactly what the reader will learn — no generic 'in this article we will'. Plain prose, no heading.";
    }
}
