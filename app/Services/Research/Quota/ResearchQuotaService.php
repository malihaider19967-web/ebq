<?php

namespace App\Services\Research\Quota;

use App\Models\ClientActivity;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Per-website monthly limits on Research-section external-API spend.
 *
 * Phase-2 reads defaults from config/services.php under
 * `services.research.limits`. Phase-4 will swap this to a per-Plan
 * `plan_research_limits` JSON column without changing call sites.
 *
 * Resources tracked:
 *   - keyword_lookup    (KeywordsEverywhere)
 *   - serp_fetch        (Serper)
 *   - llm_call          (Mistral / future LLMs)
 *   - brief             (one ContentBrief generation, may aggregate calls)
 */
class ResearchQuotaService
{
    private const ACTIVITY_PREFIX = 'research.';

    /** @return array<string, int> */
    public function defaults(): array
    {
        // Reads from /admin/research/settings via the helper (which
        // falls back to config/env if no admin override is set).
        return \App\Support\ResearchEngineSettings::quotas();
    }

    public function limit(?Website $website, string $resource): int
    {
        $default = $this->defaults()[$resource] ?? 0;

        $plan = $website?->owner?->effectivePlan();
        if ($plan !== null) {
            return $plan->researchLimit($resource, $default);
        }

        return $default;
    }

    public function used(int $websiteId, string $resource): int
    {
        return (int) ClientActivity::query()
            ->where('website_id', $websiteId)
            ->where('type', self::ACTIVITY_PREFIX.$resource)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('units_consumed');
    }

    public function remaining(?Website $website, string $resource): int
    {
        if ($website === null) {
            return $this->limit($website, $resource);
        }

        return max(0, $this->limit($website, $resource) - $this->used($website->id, $resource));
    }

    public function assertCanSpend(?Website $website, string $resource, int $units = 1): void
    {
        if ($website === null) {
            return;
        }

        if ($this->remaining($website, $resource) < $units) {
            throw new HttpException(
                402,
                "Monthly research quota exhausted for resource [{$resource}] on website #{$website->id}."
            );
        }
    }
}
