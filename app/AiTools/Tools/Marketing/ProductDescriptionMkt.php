<?php

namespace App\AiTools\Tools\Marketing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ProductDescriptionMkt extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'product-description-mkt',
            name: 'Product Description (Marketing)',
            category: Categories::MARKETING,
            description: 'Marketing-led product description — benefits and outcomes, not just specs.',
            inputs: [
                new InputField('product', 'Product name', 'text', required: true, maxLength: 200),
                new InputField('features', 'Key features (one per line)', 'textarea', required: true, maxLength: 2000),
                new InputField('audience', 'Audience', 'text', maxLength: 200),
            ],
            outputType: 'text',
            estCredits: 5,
            surfaces: [AiTool::SURFACE_STUDIO],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $product = (string) ($input['product'] ?? '');
        $features = (string) ($input['features'] ?? '');
        $audience = (string) ($input['audience'] ?? '');

        return "Product: {$product}\nAudience: {$audience}\nFeatures:\n{$features}\n\n"
            . "Write a marketing-flavored product description, 120–180 words. Lead with the outcome the customer gets, not the spec. Use one short paragraph + 3–4 short benefit-focused bullets. No buzzwords. Output prose + bullets, no heading.";
    }
}
