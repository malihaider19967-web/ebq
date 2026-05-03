<?php

namespace App\AiTools\Tools\Ecommerce;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ProductDescriptionLong extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'product-description-long',
            name: 'Product Description (Long)',
            category: Categories::ECOMMERCE,
            description: 'Long-form product page description with sections (Overview, Key Features, Use Cases).',
            inputs: [
                new InputField('product', 'Product (specs, materials, use)', 'textarea', required: true, maxLength: 3000),
                new InputField('focus_keyword', 'Focus keyword', 'text', maxLength: 200),
                new InputField('audience', 'Audience', 'text', maxLength: 200),
            ],
            outputType: 'html',
            estCredits: 9,
            surfaces: [AiTool::SURFACE_STUDIO],
            contextSignals: [AiTool::SIGNAL_BRIEF],
        );
    }

    protected function llmOptions(): array
    {
        return ['temperature' => 0.5, 'max_tokens' => 2500, 'timeout' => 90];
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $product = (string) ($input['product'] ?? '');
        $kw = (string) ($input['focus_keyword'] ?? '');
        $audience = (string) ($input['audience'] ?? '');

        return "Product: {$product}\n" . ($kw !== '' ? "Focus keyword: {$kw}\n" : '') . ($audience !== '' ? "Audience: {$audience}\n" : '')
            . "\nWrite a long-form product description in HTML. Sections (in order): <h2>Overview</h2>, <h2>Key Features</h2> (3–5 bullets), <h2>Who it's for</h2>, <h2>What's in the box</h2> (when applicable). 220–360 words total. No <h1>. Editor-portable HTML only.";
    }
}
