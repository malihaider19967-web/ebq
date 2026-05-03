<?php

namespace App\AiTools\Contracts;

/**
 * Tool metadata — what the plugin needs to render a launcher entry, a
 * form, and route the response. Stays JSON-serialisable so it ships
 * over the wire as-is.
 *
 * @phpstan-type SignalKey 'gsc' | 'brief' | 'topical_gaps' | 'entities'
 *                          | 'rank_snapshot' | 'internal_links'
 *                          | 'network_insight' | 'page_audit'
 */
final class AiToolMeta
{
    /**
     * @param  list<InputField>  $inputs
     * @param  list<string>  $surfaces             see AiTool::SURFACE_*
     * @param  list<string>  $contextSignals       which ContextBuilder signals to load (cheap if empty)
     * @param  list<string>  $supportedBlocks      Gutenberg block types this can run on (block-toolbar surface only)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $category,
        public readonly string $description,
        public readonly array $inputs,
        public readonly string $outputType = 'text',
        public readonly int $estCredits = 5,
        public readonly array $surfaces = ['studio'],
        public readonly array $contextSignals = [],
        public readonly array $supportedBlocks = [],
        public readonly ?int $cacheTtlSeconds = null,
        public readonly bool $requiresPro = true,
    ) {
    }

    /**
     * Wire shape — what the plugin sees from `GET /ai/tools`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'inputs' => array_map(static fn (InputField $f) => $f->toArray(), $this->inputs),
            'output_type' => $this->outputType,
            'est_credits' => $this->estCredits,
            'surfaces' => array_values($this->surfaces),
            'supported_blocks' => array_values($this->supportedBlocks),
            'requires_pro' => $this->requiresPro,
        ];
    }
}
