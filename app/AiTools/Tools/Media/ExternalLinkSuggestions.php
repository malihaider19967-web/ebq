<?php

namespace App\AiTools\Tools\Media;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class ExternalLinkSuggestions extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'external-link-suggestions',
            name: 'External Link Suggestions',
            category: Categories::MEDIA,
            description: 'High-authority outbound links worth citing for a topic.',
            inputs: [
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('article_text', 'Article body (for grounding)', 'textarea', maxLength: 12000),
                new InputField('count', 'How many?', 'number', default: 4),
            ],
            outputType: 'links',
            estCredits: 5,
            surfaces: [AiTool::SURFACE_STUDIO],
        );
    }

    protected function expectsJson(): bool
    {
        return true;
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $kw = (string) ($input['focus_keyword'] ?? '');
        $body = mb_substr((string) ($input['article_text'] ?? ''), 0, 8000);
        $count = max(2, min(8, (int) ($input['count'] ?? 4)));

        return "Focus keyword: {$kw}\nArticle body:\n{$body}\n\n"
            . "Suggest {$count} external link sources worth citing for this article — academic studies, primary data sources, official documentation, recognised industry publications. Avoid Wikipedia and direct competitors.\n"
            . "Return ONE JSON array: [{ \"url\": string, \"anchor\": string, \"rationale\": string }]. Only suggest sources you are confident exist; do not fabricate URLs.";
    }
}
