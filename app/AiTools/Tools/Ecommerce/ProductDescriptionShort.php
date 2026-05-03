<?php

namespace App\AiTools\Tools\Ecommerce;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ProductDescriptionShort extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'product-description-short',
            name: 'Product Description (Short)',
            category: Categories::ECOMMERCE,
            description: 'Punchy under-60-word product blurb for category pages and cards.',
            inputs: [
                new InputField('product', 'Product (specs, materials, use)', 'textarea', required: true, maxLength: 1500),
                new InputField('focus_keyword', 'Focus keyword', 'text', maxLength: 200),
            ],
            outputType: 'text',
            estCredits: 3,
            surfaces: [AiTool::SURFACE_STUDIO],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $product = (string) ($input['product'] ?? '');
        $kw = (string) ($input['focus_keyword'] ?? '');
        $kwLine = $kw !== '' ? "\nFocus keyword (use naturally): {$kw}" : '';

        return "Product: {$product}{$kwLine}\n\nWrite ONE product blurb of 30–55 words. Lead with the user outcome, not specs. Plain prose, no headings or bullets.";
    }
}
