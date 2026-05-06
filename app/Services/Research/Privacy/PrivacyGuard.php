<?php

namespace App\Services\Research\Privacy;

use App\Models\Research\NicheAggregate;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Builder;

/**
 * Centralises the rules that keep cross-client data private.
 *
 * - Aggregates are only safe to expose when sample_site_count >= the floor
 *   (default 3). Below that, a single dominant client could be reverse-
 *   engineered from the numbers.
 * - Per-client research rows must always scope to websites the user can
 *   actually view — we delegate that decision to User::canViewWebsiteId so
 *   role-based feature access stays the source of truth.
 */
class PrivacyGuard
{
    public const SAMPLE_FLOOR = 3;

    public function aggregateQuery(?int $floor = null): Builder
    {
        return NicheAggregate::query()->where('sample_site_count', '>=', $floor ?? self::SAMPLE_FLOOR);
    }

    public function canUserAccessWebsite(?User $user, int $websiteId): bool
    {
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'canViewWebsiteId')) {
            return (bool) $user->canViewWebsiteId($websiteId);
        }

        return Website::query()->whereKey($websiteId)->where('user_id', $user->id)->exists();
    }

    public function assertUserAccessWebsite(?User $user, int $websiteId): void
    {
        if (! $this->canUserAccessWebsite($user, $websiteId)) {
            abort(403, 'Forbidden — research data for this website is not visible to you.');
        }
    }
}
