<?php

namespace App\Services;

use App\Models\ClientActivity;
use Illuminate\Support\Facades\Auth;

class ClientActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function log(
        string $type,
        ?int $userId = null,
        ?int $websiteId = null,
        ?string $provider = null,
        ?array $meta = null,
        ?int $actorUserId = null,
        ?int $unitsConsumed = null
    ): void {
        ClientActivity::query()->create([
            'type' => $type,
            'user_id' => $userId,
            'actor_user_id' => $actorUserId ?? Auth::id(),
            'website_id' => $websiteId,
            'provider' => $provider,
            'meta' => $meta,
            'units_consumed' => $unitsConsumed,
        ]);
    }
}
