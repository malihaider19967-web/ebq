<?php

namespace App\AiTools\Tools\Research;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class KeywordVariations extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'keyword-variations',
            name: 'Keyword Variations',
            category: Categories::RESEARCH,
            description: 'Long-tail and locale variations of a seed keyword.',
            inputs: [
                new InputField('keyword', 'Keyword', 'text', required: true, maxLength: 200),
                new InputField('locale', 'Locale flavour', 'select', options: [
                    ['value' => 'us', 'label' => 'US English'],
                    ['value' => 'uk', 'label' => 'UK English'],
                    ['value' => 'au', 'label' => 'Australian English'],
                    ['value' => 'ca', 'label' => 'Canadian English'],
                    ['value' => 'global', 'label' => 'Locale-neutral'],
                ], default: 'global'),
            ],
            outputType: 'list',
            estCredits: 4,
            surfaces: [AiTool::SURFACE_STUDIO],
            cacheTtlSeconds: 3600,
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $keyword = (string) ($input['keyword'] ?? '');
        $locale = (string) ($input['locale'] ?? 'global');
        $localeNote = $locale === 'global'
            ? 'Mix global English variations.'
            : "Lean into {$locale} English spelling and idiom.";

        return "Generate 12 long-tail variations of: {$keyword}.\n"
            . "{$localeNote}\n"
            . "Output one per line, no numbering or bullets.";
    }
}
