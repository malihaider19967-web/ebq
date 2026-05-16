<?php

namespace App\Services\Usage;

use App\Exceptions\QuotaExceededException;
use App\Models\ClientActivity;
use App\Models\Plan;
use App\Models\RankTrackingKeyword;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Per-user accounting for paid external APIs.
 *
 * One monthly window per user, anchored to their subscription start day.
 * (Billing is yearly; usage caps reset monthly. The anchor day comes
 * from the active Cashier subscription's created_at if any, otherwise
 * the user's own created_at.)
 *
 * Providers used here:
 *   - keywords_everywhere  (KE credits — units_consumed is the credit cost)
 *   - serper               (Serper API — one call = one unit)
 *   - mistral              (Mistral LLM — units_consumed is total_tokens)
 *
 * Tracker cap is handled separately (active row count, not a monthly sum).
 */
class UsageMeter
{
    /**
     * Provider key (matches client_activities.provider) → dot-path under
     * `plans.api_limits`. `serp_api` is the legacy provider name stored
     * in the DB; the plan JSON namespaces it under `serper` to read more
     * naturally in admin UI.
     */
    private const PROVIDER_LIMIT_PATHS = [
        'keywords_everywhere' => 'keywords_everywhere.monthly_credits',
        'serp_api'            => 'serper.monthly_calls',
        'mistral'             => 'mistral.monthly_tokens',
    ];

    public function currentWindowStart(User $user): Carbon
    {
        $anchor = $this->anchorDate($user);
        return $this->monthlyWindowStart($anchor, Carbon::now());
    }

    public function consumedInWindow(User $user, string $provider): int
    {
        return (int) ClientActivity::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->where('created_at', '>=', $this->currentWindowStart($user))
            ->sum('units_consumed');
    }

    /**
     * Plan-defined cap for this provider. Null = unlimited (no plan key,
     * explicit null, or no plan resolved).
     */
    public function limit(User $user, string $provider): ?int
    {
        $path = self::PROVIDER_LIMIT_PATHS[$provider] ?? null;
        if ($path === null) {
            return null;
        }
        $plan = $user->effectivePlan();
        if (! $plan instanceof Plan) {
            return null;
        }
        return $plan->apiLimit($path);
    }

    /**
     * Remaining units in the user's current window. Null = unlimited.
     */
    public function remaining(User $user, string $provider): ?int
    {
        $limit = $this->limit($user, $provider);
        if ($limit === null) {
            return null;
        }
        return max(0, $limit - $this->consumedInWindow($user, $provider));
    }

    /**
     * Throw a 402 QuotaExceededException if spending `$units` more on
     * this provider would push the user past their plan cap. No-op for
     * users without a cap (unlimited).
     */
    public function assertCanSpend(User $user, string $provider, int $units = 1): void
    {
        $limit = $this->limit($user, $provider);
        if ($limit === null) {
            return;
        }

        $used = $this->consumedInWindow($user, $provider);
        if (($used + $units) > $limit) {
            throw new QuotaExceededException(
                provider: $provider,
                limit: $limit,
                used: $used,
                userMessage: $this->messageFor($provider, $limit),
                upgradeUrl: $this->upgradeUrl(),
            );
        }
    }

    /**
     * Active (is_active=true) tracked-keyword count for the user. Used
     * by the rank tracker hard cap.
     */
    public function activeTrackedKeywordCount(User $user): int
    {
        return RankTrackingKeyword::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->count();
    }

    public function rankTrackerCap(User $user): ?int
    {
        $plan = $user->effectivePlan();
        if (! $plan instanceof Plan) {
            return null;
        }
        return $plan->apiLimit('rank_tracker.max_active_keywords');
    }

    /**
     * Find the most recent anchor-day boundary on or before `$now`.
     * Handles month-length edge cases (e.g. anchor day 31 in a 30-day
     * month falls back to the last day of that month).
     */
    private function monthlyWindowStart(Carbon $anchor, Carbon $now): Carbon
    {
        $anchorDay = $anchor->day;
        $candidate = $this->clampDay($now->year, $now->month, $anchorDay)
            ->setTime(0, 0, 0);

        if ($candidate->greaterThan($now)) {
            $prev = $now->copy()->subMonthNoOverflow();
            $candidate = $this->clampDay($prev->year, $prev->month, $anchorDay)
                ->setTime(0, 0, 0);
        }

        // Never roll back before the subscription/account started.
        if ($candidate->lessThan($anchor->copy()->setTime(0, 0, 0))) {
            return $anchor->copy()->setTime(0, 0, 0);
        }

        return $candidate;
    }

    private function clampDay(int $year, int $month, int $day): Carbon
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        return Carbon::create($year, $month, min($day, $daysInMonth));
    }

    private function anchorDate(User $user): Carbon
    {
        $sub = $user->subscription('default');
        if ($sub && $sub->created_at) {
            return Carbon::parse($sub->created_at);
        }
        if ($user->created_at) {
            return Carbon::parse($user->created_at);
        }
        return Carbon::now()->startOfMonth();
    }

    private function messageFor(string $provider, int $limit): string
    {
        return match ($provider) {
            'keywords_everywhere' => "You've used all {$limit} Keywords Everywhere credits for this month. Upgrade your plan to keep researching.",
            'serp_api'            => "You've used all {$limit} keyword-tracking lookups for this month. Upgrade your plan to keep tracking.",
            'mistral'             => "You've used all {$limit} content-writing tokens for this month. Upgrade your plan to keep writing.",
            default               => "You've reached your monthly limit for {$provider}. Upgrade your plan to continue.",
        };
    }

    private function upgradeUrl(): string
    {
        return rtrim(config('app.url', 'https://ebq.io'), '/').'/billing';
    }
}
