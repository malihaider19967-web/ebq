<?php

namespace App\AiTools\Tools\Marketing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class AdCopy extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'ad-copy',
            name: 'Ad Copy Generator',
            category: Categories::MARKETING,
            description: 'Headlines + body for Google or Facebook ads, anchored to your top GSC queries.',
            inputs: [
                new InputField('product', 'Product / offer', 'text', required: true, maxLength: 200),
                new InputField('platform', 'Platform', 'select', options: [
                    ['value' => 'google', 'label' => 'Google Search'],
                    ['value' => 'facebook', 'label' => 'Facebook / Instagram'],
                    ['value' => 'linkedin', 'label' => 'LinkedIn'],
                ], default: 'google'),
                new InputField('benefits', 'Top 2–3 benefits', 'textarea', required: true, maxLength: 1000),
            ],
            outputType: 'json',
            estCredits: 5,
            surfaces: [AiTool::SURFACE_STUDIO],
            contextSignals: [AiTool::SIGNAL_GSC],
        );
    }

    protected function expectsJson(): bool
    {
        return true;
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $product = (string) ($input['product'] ?? '');
        $platform = (string) ($input['platform'] ?? 'google');
        $benefits = (string) ($input['benefits'] ?? '');

        $charLimits = match ($platform) {
            'google' => 'Headline ≤30 chars; description ≤90 chars.',
            'facebook' => 'Primary text ≤125 chars; headline ≤40 chars.',
            default => 'Intro ≤150 chars; headline ≤70 chars.',
        };

        $gsc = '';
        if (is_array($context->gscTopQueries) && $context->gscTopQueries !== []) {
            $gsc = "\n\nQueries the brand already gets organic traffic for (lean in if relevant):\n";
            foreach (array_slice($context->gscTopQueries, 0, 5) as $q) {
                $gsc .= "- {$q['query']}\n";
            }
        }

        return "Product: {$product}\nPlatform: {$platform}\nBenefits: {$benefits}\n\n"
            . "Return ONE JSON object: { \"headlines\": string[5], \"descriptions\": string[3] }. {$charLimits} Hooks vary across the 5 headlines (curiosity, benefit, problem, social proof, urgency)."
            . $gsc;
    }
}
