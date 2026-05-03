<?php

namespace App\AiTools\Tools\Marketing;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class Tweet extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'tweet',
            name: 'Tweet / X Thread',
            category: Categories::MARKETING,
            description: 'A single tweet or a 3–7 tweet thread.',
            inputs: [
                new InputField('topic', 'Topic / hook', 'textarea', required: true, maxLength: 1500),
                new InputField('format', 'Format', 'select', options: [
                    ['value' => 'single', 'label' => 'Single tweet'],
                    ['value' => 'thread', 'label' => 'Thread (5 tweets)'],
                ], default: 'single'),
            ],
            outputType: 'list',
            estCredits: 4,
            surfaces: [AiTool::SURFACE_STUDIO],
        );
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $topic = (string) ($input['topic'] ?? '');
        $format = (string) ($input['format'] ?? 'single');

        if ($format === 'single') {
            return "Topic: {$topic}\n\nWrite ONE tweet ≤270 characters. Punchy first line, no hashtags, no emoji. Output the tweet only.";
        }

        return "Topic: {$topic}\n\n"
            . "Write a 5-tweet X thread. Tweet 1 hooks; tweets 2–4 build the argument with concrete examples; tweet 5 closes with the takeaway. Each ≤270 characters. Output one tweet per line, no numbering, no thread emoji.";
    }
}
