<?php

namespace App\AiTools\Tools\Ecommerce;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ProductFaqs extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'product-faqs',
            name: 'Product FAQs',
            category: Categories::ECOMMERCE,
            description: '5 product page FAQs grounded in real PAA questions.',
            inputs: [
                new InputField('product', 'Product (specs, materials, use)', 'textarea', required: true, maxLength: 3000),
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
            ],
            outputType: 'faq',
            estCredits: 5,
            surfaces: [AiTool::SURFACE_STUDIO],
            contextSignals: [AiTool::SIGNAL_RANK_SNAPSHOT],
        );
    }

    protected function expectsJson(): bool
    {
        return true;
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $product = (string) ($input['product'] ?? '');
        $kw = (string) ($input['focus_keyword'] ?? '');

        $paa = '';
        if (is_array($context->rankSnapshot) && ! empty($context->rankSnapshot['people_also_ask'])) {
            $paa = "\n\nLive PAA questions for this product type:\n";
            foreach (array_slice($context->rankSnapshot['people_also_ask'], 0, 5) as $q) {
                if (is_string($q)) {
                    $paa .= "- {$q}\n";
                }
            }
        }

        return "Product: {$product}\nFocus keyword: {$kw}\n\n"
            . "Return ONE JSON array of 5 FAQs: [{ \"question\": string, \"answer\": string }]. Common buying-decision questions — sizing, materials, returns, compatibility, care. Answers 30–60 words, plain prose."
            . $paa;
    }
}
