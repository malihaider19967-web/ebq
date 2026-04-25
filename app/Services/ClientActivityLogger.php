<?php

namespace App\Services;

use App\Models\ClientActivity;
use App\Models\Website;
use Illuminate\Support\Facades\Auth;

class ClientActivityLogger
{
    /**
     * Per-request cache so a 100-keyword KE call doesn't hit the websites
     * table 100 times resolving the same owner.
     *
     * @var array<int, ?int>
     */
    private array $websiteOwnerCache = [];

    /**
     * Log an activity row.
     *
     * Attribution model:
     *   - `actor_user_id` = whoever triggered the call (Auth::id() when in a
     *     web request, the explicit override otherwise). Captures "who
     *     clicked refresh / ran the job".
     *   - `user_id`       = the billable client. When `$websiteId` is given,
     *     this is forced to the website's owner so spend always attributes
     *     to the paying account, not to a team member or impersonator. The
     *     `$userId` argument is only used as a fallback when there's no
     *     website context.
     *
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
        $billedUserId = $websiteId !== null
            ? ($this->resolveWebsiteOwner($websiteId) ?? $userId)
            : $userId;

        ClientActivity::query()->create([
            'type' => $type,
            'user_id' => $billedUserId,
            'actor_user_id' => $actorUserId ?? Auth::id(),
            'website_id' => $websiteId,
            'provider' => $provider,
            'meta' => $meta,
            'units_consumed' => $unitsConsumed,
        ]);
    }

    private function resolveWebsiteOwner(int $websiteId): ?int
    {
        if (array_key_exists($websiteId, $this->websiteOwnerCache)) {
            return $this->websiteOwnerCache[$websiteId];
        }

        $ownerId = Website::query()
            ->whereKey($websiteId)
            ->value('user_id');

        return $this->websiteOwnerCache[$websiteId] = $ownerId !== null ? (int) $ownerId : null;
    }
}
