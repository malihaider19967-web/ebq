<?php

namespace App\Models\Concerns;

use App\Support\ShardContext;

/**
 * Marks a model as living on the TENANT tier (per-website fact data, sharded by
 * owner). At query time it asks {@see ShardContext} which node connection the
 * active website resolves to; `null` means Eloquent's default connection
 * (single-node / CLI / tests) so behaviour is unchanged until nodes exist.
 */
trait UsesTenantConnection
{
    public function getConnectionName(): ?string
    {
        return app(ShardContext::class)->tenantConnection() ?? parent::getConnectionName();
    }
}
