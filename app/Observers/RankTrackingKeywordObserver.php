<?php

namespace App\Observers;

use App\Exceptions\QuotaExceededException;
use App\Jobs\FetchKeywordMetricsJob;
use App\Models\RankTrackingKeyword;
use App\Models\User;
use App\Models\Website;
use App\Services\Usage\UsageMeter;

/**
 * When a tracked keyword is created, queue a Keywords Everywhere lookup so the
 * UI has volume/CPC/competition on first render. Single global fetch per
 * keyword — per-country lookups are not a user-facing surface.
 *
 * Also enforces the per-plan active-keyword cap as defense-in-depth — Livewire
 * UI already blocks at the form level, this catches API-route additions
 * (Plugin HQ, future integrations) that bypass the Livewire path.
 */
class RankTrackingKeywordObserver
{
    public function creating(RankTrackingKeyword $keyword): void
    {
        // Only gate brand-new active rows; toggling an existing row from
        // inactive→active goes through `updating` and the user already
        // owns that slot.
        if (! $keyword->is_active) {
            return;
        }

        $billedUser = $this->resolveBilledUser($keyword);
        if ($billedUser === null) {
            return;
        }

        $meter = app(UsageMeter::class);
        $cap = $meter->rankTrackerCap($billedUser);
        if ($cap === null) {
            return;
        }

        if ($meter->activeTrackedKeywordCount($billedUser) >= $cap) {
            throw new QuotaExceededException(
                provider: 'rank_tracker',
                limit: $cap,
                used: $cap,
                userMessage: "You're at your plan's limit of {$cap} active tracked keywords. Pause or remove keywords to add new ones, or upgrade your plan.",
                upgradeUrl: rtrim(config('app.url', 'https://ebq.io'), '/').'/billing',
            );
        }
    }

    public function created(RankTrackingKeyword $keyword): void
    {
        $text = trim((string) $keyword->keyword);
        if ($text === '') {
            return;
        }

        FetchKeywordMetricsJob::dispatch([$text], 'global');
    }

    private function resolveBilledUser(RankTrackingKeyword $keyword): ?User
    {
        if ($keyword->website_id) {
            $owner = Website::find($keyword->website_id)?->owner;
            if ($owner instanceof User) {
                return $owner;
            }
        }
        return $keyword->user_id ? User::find($keyword->user_id) : null;
    }
}
