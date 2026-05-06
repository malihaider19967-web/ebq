<?php

namespace App\Services\Research\Niche;

/**
 * Phase-2 ships rule-based + SERP-overlap. Flipping
 * RESEARCH_EMBEDDINGS_ENABLED + binding a real implementation switches
 * KeywordToNicheMapper / ClusteringService to embedding similarity
 * without changing call sites.
 */
interface EmbeddingProvider
{
    public function isAvailable(): bool;

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>  one vector per input, in the same order
     */
    public function embed(array $texts): array;
}
