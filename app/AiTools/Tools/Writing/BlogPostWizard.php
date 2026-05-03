<?php

namespace App\AiTools\Tools\Writing;

use App\AiTools\Categories;
use App\AiTools\Contracts\AiTool;
use App\AiTools\Contracts\AiToolMeta;
use App\AiTools\Contracts\AiToolResult;
use App\AiTools\Contracts\InputField;
use App\AiTools\Contracts\ToolContext;
use Illuminate\Support\Carbon;

/**
 * Marker tool for the multi-step Blog Post Wizard.
 *
 * The wizard isn't a single LLM call — it's a project lifecycle (topic
 * → brief → images → summary → review) with persistence in
 * `writer_projects`. The plugin's tool launcher detects this tool id
 * and opens the existing wizard UI directly; this class exists so the
 * registry has a metadata entry for it.
 *
 * `execute()` is a no-op — calling /run for this tool just returns a
 * directive payload pointing the plugin at the wizard endpoints.
 */
final class BlogPostWizard implements AiTool
{
    public function meta(): AiToolMeta
    {
        return new AiToolMeta(
            id: 'blog-post-wizard',
            name: 'Blog Post Wizard',
            category: Categories::WRITING,
            description: 'Full multi-step blog post: topic → brief → images → summary → review. Saves a project you can resume.',
            inputs: [],
            outputType: 'json',
            estCredits: 60,
            surfaces: [AiTool::SURFACE_STUDIO],
            contextSignals: [],
            cacheTtlSeconds: null,
        );
    }

    public function execute(array $input, ToolContext $context): AiToolResult
    {
        return new AiToolResult(
            ok: true,
            outputType: 'json',
            value: [
                'redirect' => 'wizard',
                'wizard_endpoint_prefix' => '/api/v1/hq/writer-projects',
                'message' => 'Open the multi-step Blog Post Wizard.',
            ],
            usage: ['prompt' => 0, 'completion' => 0, 'total' => 0],
            generatedAt: Carbon::now()->toIso8601String(),
        );
    }
}
