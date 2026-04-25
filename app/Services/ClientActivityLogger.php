<?php

namespace App\Services;

use App\Models\ClientActivity;
use App\Support\TeamPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Single funnel for writing rows into `client_activities`.
 *
 * Critical attribution rules — these decide who shows up on the admin
 * "API usage" page:
 *
 *   user_id        = the BILLED account (who pays for this work).
 *                    When `website_id` is set, this is FORCED to the
 *                    website's owner (role='owner' on `website_user`,
 *                    falling back to `websites.user_id` for legacy rows
 *                    that pre-date team roles). Anything caller-supplied
 *                    is treated as a fallback only.
 *
 *   actor_user_id  = the human who *triggered* the call. We prefer the
 *                    real admin's id during impersonation
 *                    (`session('impersonator_id')`) so an admin acting
 *                    "as" a client doesn't pollute the actor column with
 *                    the client's id.
 */
class ClientActivityLogger
{
    /**
     * Per-request cache so a 100-keyword KE call doesn't hit the DB
     * 100 times resolving the same owner.
     *
     * @var array<int, ?int>
     */
    private array $websiteOwnerCache = [];

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
        $billedUserId = $websiteId !== null
            ? ($this->resolveWebsiteOwner($websiteId) ?? $userId)
            : $userId;

        ClientActivity::query()->create([
            'type'            => $type,
            'user_id'         => $billedUserId,
            'actor_user_id'   => $actorUserId ?? $this->resolveActor(),
            'website_id'      => $websiteId,
            'provider'        => $provider,
            'meta'            => $meta,
            'units_consumed'  => $unitsConsumed,
        ]);
    }

    /**
     * Real human triggering the call. During admin impersonation,
     * `Auth::id()` returns the *impersonated* client, so we prefer the
     * admin's id from the impersonation session if present.
     */
    private function resolveActor(): ?int
    {
        try {
            $impersonatorId = (int) session('impersonator_id', 0);
            if ($impersonatorId > 0) {
                return $impersonatorId;
            }
        } catch (\Throwable) {
            // No session (queue worker, CLI) — fall through.
        }

        return Auth::id();
    }

    /**
     * Resolve the billed Owner of a website.
     *
     * Lookup order:
     *   1. `website_user` row with role='owner' (the modern, team-aware
     *      source of truth — what the team UI uses).
     *   2. `websites.user_id` (the legacy "creator" field — fallback for
     *      older rows where the pivot Owner was never written).
     */
    private function resolveWebsiteOwner(int $websiteId): ?int
    {
        if (array_key_exists($websiteId, $this->websiteOwnerCache)) {
            return $this->websiteOwnerCache[$websiteId];
        }

        $ownerId = DB::table('website_user')
            ->where('website_id', $websiteId)
            ->where('role', TeamPermissions::ROLE_OWNER)
            ->value('user_id');

        if ($ownerId === null) {
            $ownerId = DB::table('websites')
                ->where('id', $websiteId)
                ->value('user_id');
        }

        return $this->websiteOwnerCache[$websiteId] = $ownerId !== null ? (int) $ownerId : null;
    }
}
