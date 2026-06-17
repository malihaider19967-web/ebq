<?php

namespace App\Models\Concerns;

use App\Support\ShardContext;

/**
 * Marks a model as living on the CRAWL tier (per-domain crawl data, sharded by
 * `crawl_site`). At query time it asks {@see ShardContext} which crawl node the
 * active crawl-site resolves to; `null` means Eloquent's default connection
 * (single-node / CLI / tests) so behaviour is unchanged until nodes exist.
 */
trait UsesCrawlConnection
{
    public function getConnectionName(): ?string
    {
        return app(ShardContext::class)->crawlConnection() ?? parent::getConnectionName();
    }
}
