<?php

namespace App\AiTools\Tools\Ecommerce;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ProductTitle extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'product-title',
            name: 'Product Title',
            category: Categories::ECOMMERCE,
            description: '3 product title variants — search-optimised but readable.',
            inputs: [
                new InputField('product', 'Product (specs, materials, size)', 'textarea', required: true, maxLength: 1500),
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('brand', 'Brand (optional)', 'text', maxLength: 100),
            ],
            outputType: 'titles',
            estCredits: 3,
            surfaces: [AiTool::SURFACE_STUDIO],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $product = (string) ($input['product'] ?? '');
        $kw = (string) ($input['focus_keyword'] ?? '');
        $brand = (string) ($input['brand'] ?? '');

        return "Product: {$product}\nFocus keyword: {$kw}\n" . ($brand !== '' ? "Brand: {$brand}\n" : '')
            . "\nGenerate 3 product title variants. Each ≤80 chars. Format: Brand + Product + Key Attribute (size/colour/material/use). Focus keyword appears naturally near the start. One per line, no numbering, no quotes.";
    }
}
