<?php

namespace App\AiTools\Tools\Misc;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class Definition extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'definition',
            name: 'Definition Generator',
            category: Categories::MISC,
            description: 'Dictionary-style definition for a term, snippet-friendly.',
            inputs: [
                new InputField('term', 'Term', 'text', required: true, maxLength: 200),
                new InputField('context', 'Context (optional — disambiguates polysemous terms)', 'text', maxLength: 200),
                new InputField('audience', 'Reader level', 'select', options: [
                    ['value' => 'beginner', 'label' => 'Beginner'],
                    ['value' => 'general', 'label' => 'General'],
                    ['value' => 'expert', 'label' => 'Expert'],
                ], default: 'general'),
            ],
            outputType: 'text',
            estCredits: 2,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph'],
            cacheTtlSeconds: 3600,
        );
    }

    protected function llmOptions(): array
    {
        return ['temperature' => 0.3, 'max_tokens' => 400, 'timeout' => 30];
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $term = (string) ($input['term'] ?? '');
        $ctx = (string) ($input['context'] ?? '');
        $audience = (string) ($input['audience'] ?? 'general');

        return "Term: {$term}\n" . ($ctx !== '' ? "Context: {$ctx}\n" : '')
            . "Reader level: {$audience}\n\n"
            . "Write a definition of 30–50 words. Snippet-friendly — open with the term followed by 'is' or 'are', then the core definition, then one clarifying sentence. Plain prose, no list.";
    }
}
