<?php

namespace App\AiTools\Tools\Marketing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class SocialPost extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'social-post',
            name: 'Social Media Post',
            category: Categories::MARKETING,
            description: 'LinkedIn / Facebook / Instagram post promoting a piece of content or offer.',
            inputs: [
                new InputField('platform', 'Platform', 'select', options: [
                    ['value' => 'linkedin', 'label' => 'LinkedIn'],
                    ['value' => 'facebook', 'label' => 'Facebook'],
                    ['value' => 'instagram', 'label' => 'Instagram'],
                ], default: 'linkedin'),
                new InputField('topic', 'Topic / link summary', 'textarea', required: true, maxLength: 1500),
                new InputField('cta_url', 'Link (optional)', 'url'),
                new InputField('include_hashtags', 'Include hashtags?', 'select', options: [
                    ['value' => 'yes', 'label' => 'Yes — 3–5 relevant tags'],
                    ['value' => 'no', 'label' => 'No'],
                ], default: 'yes'),
            ],
            outputType: 'text',
            estCredits: 4,
            surfaces: [AiTool::SURFACE_STUDIO],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $platform = (string) ($input['platform'] ?? 'linkedin');
        $topic = (string) ($input['topic'] ?? '');
        $url = (string) ($input['cta_url'] ?? '');
        $tags = (string) ($input['include_hashtags'] ?? 'yes');

        $rules = match ($platform) {
            'linkedin' => 'LinkedIn post — 80–150 words, professional but human. Use line breaks for skim. No emoji storms.',
            'facebook' => 'Facebook post — 50–120 words, conversational. One emoji max.',
            default => 'Instagram caption — 50–100 words, voice-led. Up to 2 emojis. Hooks in line 1.',
        };

        $tagInstr = $tags === 'yes' ? ' End with 3–5 relevant hashtags on a separate line.' : '';
        $linkInstr = $url !== '' ? "\nInclude this link verbatim near the end: {$url}" : '';

        return "Topic: {$topic}\n\n{$rules}{$tagInstr}{$linkInstr}\n\nOutput the post text only.";
    }
}
