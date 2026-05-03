<?php

namespace App\AiTools\Tools\Misc;

use App\AiTools\AbstractAiTool;
use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;

final class SentenceGenerator extends AbstractAiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'sentence-generator',
            name: 'Sentence Generator',
            category: Categories::MISC,
            description: 'A single sentence with constraints (length, includes a phrase, etc.).',
            inputs: [
                new InputField('topic', 'Topic / what to say', 'textarea', required: true, maxLength: 1000),
                new InputField('must_include', 'Must include this phrase (optional)', 'text', maxLength: 100),
                new InputField('max_words', 'Max words', 'number', default: 25),
            ],
            outputType: 'text',
            estCredits: 1,
            surfaces: [AiTool::SURFACE_STUDIO],
        );
    }

    protected function llmOptions(): array
    {
        return ['temperature' => 0.5, 'max_tokens' => 200, 'timeout' => 30];
    }

    protected function buildUserPrompt(array $input, ToolContext $context): string
    {
        $topic = (string) ($input['topic'] ?? '');
        $include = (string) ($input['must_include'] ?? '');
        $max = max(5, min(50, (int) ($input['max_words'] ?? 25)));

        $rule = $include !== '' ? " Must include the phrase '{$include}'." : '';

        return "Topic: {$topic}\n\nWrite ONE sentence ≤{$max} words.{$rule} Output only the sentence.";
    }
}
