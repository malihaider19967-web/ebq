<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class RewriteContent extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'rewrite-content',
            name: 'Rewrite Content',
            category: Categories::WRITING,
            description: 'Rewrite a passage in the same meaning — clearer, tighter, on-brand.',
            inputs: [
                new InputField('text', 'Text to rewrite', 'textarea', required: true, maxLength: 8000),
                // Optional callers (e.g. the SmartBadge bulb) can pass a
                // phrase / list of phrases the rewrite MUST naturally
                // weave in. Without this, plain rewrite has no signal
                // to know the focus keyword needs to be included — so
                // the SEO bulb's "weave 'X' into this paragraph" hint
                // would loop forever as the rewrite never inserts X.
                new InputField('must_include', 'Phrases the rewrite must include', 'tags',
                    placeholder: 'comma-separated; e.g. focus keyword'),
            ],
            outputType: 'text',
            estCredits: 5,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/paragraph', 'core/quote', 'core/list'],
            contextSignals: [AiTool::SIGNAL_SEO_ANALYSIS],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $text = (string) ($input['text'] ?? '');

        $mustInclude = [];
        $raw = $input['must_include'] ?? null;
        if (is_array($raw)) {
            foreach ($raw as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $mustInclude[] = trim($v);
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            foreach (preg_split('/[,\n]+/', $raw) as $v) {
                $v = trim((string) $v);
                if ($v !== '') {
                    $mustInclude[] = $v;
                }
            }
        }
        $mustInclude = array_values(array_unique($mustInclude));

        $imperative = '';
        if ($mustInclude !== []) {
            $list = implode(', ', array_map(static fn ($s) => "\"{$s}\"", array_slice($mustInclude, 0, 4)));
            $imperative = "\n\nIMPORTANT — the rewrite MUST naturally include the following phrase(s) at least once each (use them in prose, never keyword-stuff): {$list}.";
        }

        return "Rewrite the following passage. Preserve EVERY claim and the same approximate length. Improve clarity, remove filler, and match the brand voice. Output the rewritten passage only — no commentary."
            . $imperative
            . "\n\n---\n{$text}\n---";
    }
}
