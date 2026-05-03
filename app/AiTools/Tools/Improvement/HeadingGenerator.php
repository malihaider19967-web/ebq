<?php

namespace App\AiTools\Tools\Improvement;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class HeadingGenerator extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'heading-generator',
            name: 'Heading Generator',
            category: Categories::IMPROVEMENT,
            description: '5 H2 alternatives for a passage of body content.',
            inputs: [
                new InputField('passage', 'Section body', 'textarea', required: true, maxLength: 4000),
                new InputField('focus_keyword', 'Focus keyword (optional)', 'text', maxLength: 200),
            ],
            outputType: 'list',
            estCredits: 3,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/heading'],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $passage = (string) ($input['passage'] ?? '');
        $kw = (string) ($input['focus_keyword'] ?? '');
        $kwHint = $kw !== '' ? "\nFocus keyword (use naturally in at least 2 of the 5): {$kw}" : '';

        return "Generate 5 H2 heading alternatives for this passage. Each ≤70 characters, search-friendly, distinct angle (literal / question / promise / contrast / outcome). One per line, no numbering, no quotes.{$kwHint}\n\n---\n{$passage}\n---";
    }
}
