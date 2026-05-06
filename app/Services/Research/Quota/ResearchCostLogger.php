<?php

namespace App\Services\Research\Quota;

use App\Services\ClientActivityLogger;

/**
 * Thin pass-through to ClientActivityLogger that namespaces every research
 * activity row with `research.*` types so the admin usage page can split
 * Research-section cost out from the rest of the platform.
 */
class ResearchCostLogger
{
    public function __construct(private readonly ClientActivityLogger $base) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(
        string $resource,
        ?int $websiteId,
        string $provider,
        array $meta = [],
        int $units = 1,
        ?int $userId = null,
    ): void {
        $this->base->log(
            type: 'research.'.$resource,
            userId: $userId,
            websiteId: $websiteId,
            provider: $provider,
            meta: $meta,
            unitsConsumed: $units,
        );
    }
}
