<?php

namespace App\AiTools\Tools\Media;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class AltText extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'alt-text',
            name: 'Image Alt Text',
            category: Categories::MEDIA,
            description: 'Accessibility-grade image alt text. SEO-aware when a focus keyword is given.',
            inputs: [
                new InputField('image_url', 'Image URL', 'url', required: true),
                new InputField('caption', 'What the image shows (optional but helpful)', 'textarea', maxLength: 500),
                new InputField('focus_keyword', 'Page focus keyword (optional)', 'text', maxLength: 200),
            ],
            outputType: 'text',
            estCredits: 2,
            surfaces: [AiTool::SURFACE_STUDIO, AiTool::SURFACE_BLOCK_TOOLBAR],
            supportedBlocks: ['core/image', 'core/cover', 'core/media-text'],
        );
    }

    protected function llmOptions(): array
    {
        return ['temperature' => 0.3, 'max_tokens' => 200, 'timeout' => 30];
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $url = (string) ($input['image_url'] ?? '');
        $caption = (string) ($input['caption'] ?? '');
        $kw = (string) ($input['focus_keyword'] ?? '');

        return "Image URL: {$url}\n"
            . ($caption !== '' ? "Caption / context: {$caption}\n" : '')
            . ($kw !== '' ? "Page focus keyword: {$kw}\n" : '')
            . "\nWrite ONE alt-text string ≤120 characters. Describe what is literally in the image. If the focus keyword fits naturally include it once — never stuff. No 'image of' or 'picture of' preamble. Output the alt text only.";
    }
}
