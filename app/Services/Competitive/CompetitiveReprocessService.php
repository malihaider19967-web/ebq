<?php

namespace App\Services\Competitive;

use App\Models\KeywordGapAnalysis;
use App\Models\Website;

/**
 * Upgrades a website's already-generated competitive artifacts to the higher
 * fidelity tier when Search Console connects after the fact. The expensive part
 * (competitor discovery) is gated; the cheap part (re-bucketing stored gap rows
 * with real positions) needs no new discovery calls at all.
 */
class CompetitiveReprocessService
{
    public function __construct(
        private KeywordGapService $gap,
        private CompetitorDiscoveryService $discovery,
    ) {
    }

    public function reprocess(int $websiteId): void
    {
        $website = Website::query()->find($websiteId);
        if (! $website instanceof Website || ! $website->hasGsc()) {
            return;
        }

        // 1) FREE: re-bucket every completed gap analysis with GSC positions.
        KeywordGapAnalysis::query()
            ->where('website_id', $websiteId)
            ->where('status', KeywordGapAnalysis::STATUS_COMPLETED)
            ->orderByDesc('id')
            ->each(function (KeywordGapAnalysis $analysis): void {
                $this->gap->reprocessWithGsc($analysis);
            });

        // 2) Gated SERP spend: a forced discovery re-run now has real GSC seeds
        //    (far better than manual seeds). queueRunIfStale(force) still respects
        //    the in-flight guard so we never stack runs.
        $this->discovery->queueRunIfStale($website, $website->user_id, force: true);
    }
}
