<?php

namespace App\AiTools\Tools\Marketing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class CtaGenerator extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'cta-generator',
            name: 'Call-To-Action Generator',
            category: Categories::MARKETING,
            description: '5 CTA variants tuned for the page intent.',
            inputs: [
                new InputField('offer', 'Offer / page intent', 'textarea', required: true, maxLength: 1000),
                new InputField('intent', 'Funnel stage', 'select', options: [
                    ['value' => 'awareness', 'label' => 'Awareness — softest'],
                    ['value' => 'consideration', 'label' => 'Consideration'],
                    ['value' => 'decision', 'label' => 'Decision — direct'],
                ], default: 'consideration'),
            ],
            outputType: 'list',
            estCredits: 2,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/button'],
            contextSignals: [AiTool::SIGNAL_SEO_ANALYSIS],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $offer = (string) ($input['offer'] ?? '');
        $intent = (string) ($input['intent'] ?? 'consideration');

        $rule = match ($intent) {
            'awareness' => 'Soft, exploratory verbs (See, Discover, Explore, Read).',
            'decision' => 'Direct, action verbs (Get, Start, Buy, Try, Book).',
            default => 'Mid-funnel verbs (Compare, Learn, Watch, Download).',
        };

        return "Offer: {$offer}\nIntent: {$intent}\n\n"
            . "Generate 5 CTA button labels. Each ≤4 words. {$rule} One per line, no quotes, no numbering.";
    }
}
