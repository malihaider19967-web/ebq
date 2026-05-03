<?php

namespace App\AiTools\Tools\Ecommerce;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ProductFeatures extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'product-features',
            name: 'Product Features List',
            category: Categories::ECOMMERCE,
            description: 'Bullet list of features with one-line benefits.',
            inputs: [
                new InputField('product', 'Product (specs, materials, use)', 'textarea', required: true, maxLength: 3000),
                new InputField('count', 'How many features?', 'number', default: 6),
            ],
            outputType: 'list',
            estCredits: 3,
            surfaces: [AiTool::SURFACE_STUDIO],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $product = (string) ($input['product'] ?? '');
        $count = max(3, min(12, (int) ($input['count'] ?? 6)));

        return "Product: {$product}\n\nList {$count} features. Each line: 'Feature name — one-sentence customer benefit.' One per line, no numbering, no quotes.";
    }
}
