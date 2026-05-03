<?php

namespace App\AiTools\Contracts;

/**
 * Every AI Studio tool implements this contract. The runner
 * (`App\Services\AiToolRunner`) is the only caller; tools never invoke
 * each other directly — composition happens at the controller layer.
 *
 * Tools are stateless services; constructor dependencies (LLM client,
 * Serper, etc.) come from the container.
 */
interface AiTool
{
    public const SURFACE_STUDIO        = 'studio';
    public const SURFACE_BLOCK_TOOLBAR = 'block-toolbar';
    public const SURFACE_SIDEBAR       = 'sidebar';
    public const SURFACE_BULK          = 'bulk-actions';

    public const SIGNAL_GSC             = 'gsc';
    public const SIGNAL_BRIEF           = 'brief';
    public const SIGNAL_TOPICAL_GAPS    = 'topical_gaps';
    public const SIGNAL_ENTITIES        = 'entities';
    public const SIGNAL_RANK_SNAPSHOT   = 'rank_snapshot';
    public const SIGNAL_INTERNAL_LINKS  = 'internal_links';
    public const SIGNAL_NETWORK_INSIGHT = 'network_insight';
    public const SIGNAL_PAGE_AUDIT      = 'page_audit';

    /**
     * Lightweight, computed-from-input SEO state. Powers the
     * "writer must honour the SEO analysis" rule: the prompt sees
     * what's missing (focus kw absence, density gap, weak structure,
     * missing entities) and produces content that closes those gaps
     * naturally.
     */
    public const SIGNAL_SEO_ANALYSIS    = 'seo_analysis';

    public function meta(): AiToolMeta;

    /**
     * @param  array<string, mixed>  $input  validated against meta()->inputs by the runner
     */
    public function execute(array $input, ToolContext $context): AiToolResult;
}
