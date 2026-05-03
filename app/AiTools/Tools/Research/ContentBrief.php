<?php

namespace App\AiTools\Tools\Research;

use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\AiToolResult;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;
use App\Services\AiContentBriefService;
use Illuminate\Support\Carbon;

/**
 * Wraps the existing AiContentBriefService so the brief generator
 * surfaces in AI Studio alongside every other tool. Uses the same
 * Serper-backed brief that the Gutenberg sidebar's "Brief" tab and
 * the Blog Post Wizard rely on — all share one cache.
 */
final class ContentBrief implements AiTool
{
    public function __construct(private readonly AiContentBriefService $brief)
    {
    }

    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'content-brief',
            name: 'Content Brief',
            category: Categories::RESEARCH,
            description: 'Full SERP-grounded brief: outline, subtopics, must-have entities, PAA, recommended depth.',
            inputs: [
                new InputField('focus_keyword', 'Focus keyword', 'text', required: true, maxLength: 200),
                new InputField('country', 'Country', 'text', placeholder: 'us', maxLength: 2),
                new InputField('language', 'Language', 'text', placeholder: 'en', maxLength: 5),
            ],
            outputType: 'json',
            estCredits: 30,
            surfaces: [AiTool::SURFACE_STUDIO],
            contextSignals: [],
            cacheTtlSeconds: null, // service caches internally
        );
    }

    public function execute(array $input, ToolContext $context): AiToolResult
    {
        $kw = (string) ($input['focus_keyword'] ?? '');
        if (mb_strlen($kw) < 2) {
            return AiToolResult::fail('invalid_input', 'Focus keyword is required.', 'json');
        }

        @set_time_limit(180);

        $res = $this->brief->brief($context->website, 0, [
            'focus_keyword' => $kw,
            'country' => $input['country'] ?? null,
            'language' => $input['language'] ?? null,
        ]);

        if (! is_array($res) || ($res['ok'] ?? false) !== true) {
            return AiToolResult::fail(
                error: is_array($res) ? (string) ($res['error'] ?? 'brief_failed') : 'brief_failed',
                message: 'The brief could not be generated. Try again with a more specific focus keyword.',
                outputType: 'json',
            );
        }

        return new AiToolResult(
            ok: true,
            outputType: 'json',
            value: $res['brief'] ?? [],
            usage: ['prompt' => 0, 'completion' => 0, 'total' => 0],
            cached: (bool) ($res['cached'] ?? false),
            generatedAt: Carbon::now()->toIso8601String(),
            diagnostics: [
                'cached' => (bool) ($res['cached'] ?? false),
            ],
        );
    }
}
