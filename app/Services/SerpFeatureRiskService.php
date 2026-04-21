<?php

namespace App\Services;

use App\Models\RankTrackingKeyword;
use App\Models\RankTrackingSnapshot;

class SerpFeatureRiskService
{
    /**
     * SERP features that commonly absorb organic clicks away from the #1 result.
     *
     * @var list<string>
     */
    public const RISK_FEATURES = ['answerBox', 'knowledgeGraph', 'topStories', 'shopping'];

    /**
     * For one keyword: does the latest snapshot show a risk feature we don't own?
     * Does the keyword appear to have LOST a feature it used to have?
     *
     * @return array{
     *     at_risk: bool,
     *     lost_feature: bool,
     *     features_present: list<string>,
     *     features_lost: list<string>,
     * }
     */
    public function riskFor(RankTrackingKeyword $keyword): array
    {
        $latest = RankTrackingSnapshot::query()
            ->where('rank_tracking_keyword_id', $keyword->id)
            ->where('status', 'ok')
            ->orderByDesc('checked_at')
            ->first();

        if (! $latest) {
            return ['at_risk' => false, 'lost_feature' => false, 'features_present' => [], 'features_lost' => []];
        }

        $present = array_values(array_intersect(self::RISK_FEATURES, (array) $latest->serp_features));
        $weOwnTop = $latest->position !== null && (int) $latest->position <= 1;
        $atRisk = ! empty($present) && ! $weOwnTop;

        $prior = RankTrackingSnapshot::query()
            ->where('rank_tracking_keyword_id', $keyword->id)
            ->where('status', 'ok')
            ->where('id', '!=', $latest->id)
            ->orderByDesc('checked_at')
            ->first();

        $lost = [];
        if ($prior) {
            $priorFeatures = array_values(array_intersect(self::RISK_FEATURES, (array) $prior->serp_features));
            $lost = array_values(array_diff($priorFeatures, (array) $latest->serp_features));
        }

        return [
            'at_risk' => $atRisk,
            'lost_feature' => ! empty($lost),
            'features_present' => $present,
            'features_lost' => $lost,
        ];
    }

    /**
     * Risk map for every active keyword on a website, keyed by keyword id.
     * Uses one snapshot query across all keywords so it's usable on index views.
     *
     * @return array<int, array{at_risk: bool, lost_feature: bool, features_present: list<string>, features_lost: list<string>}>
     */
    public function riskMapForWebsite(int $websiteId): array
    {
        $keywordIds = RankTrackingKeyword::query()
            ->where('website_id', $websiteId)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (empty($keywordIds)) {
            return [];
        }

        // Pull the most recent two "ok" snapshots per keyword via PHP-side ranking.
        $snapshots = RankTrackingSnapshot::query()
            ->whereIn('rank_tracking_keyword_id', $keywordIds)
            ->where('status', 'ok')
            ->orderBy('rank_tracking_keyword_id')
            ->orderByDesc('checked_at')
            ->get(['id', 'rank_tracking_keyword_id', 'position', 'serp_features', 'checked_at']);

        $out = [];
        foreach ($keywordIds as $id) {
            $out[$id] = ['at_risk' => false, 'lost_feature' => false, 'features_present' => [], 'features_lost' => []];
        }
        $seen = [];
        foreach ($snapshots as $snap) {
            $kid = (int) $snap->rank_tracking_keyword_id;
            $seen[$kid] = ($seen[$kid] ?? 0) + 1;
            if ($seen[$kid] === 1) {
                $present = array_values(array_intersect(self::RISK_FEATURES, (array) $snap->serp_features));
                $weOwnTop = $snap->position !== null && (int) $snap->position <= 1;
                $out[$kid]['features_present'] = $present;
                $out[$kid]['at_risk'] = ! empty($present) && ! $weOwnTop;
                $out[$kid]['_latest_features'] = (array) $snap->serp_features;
            } elseif ($seen[$kid] === 2) {
                $priorFeatures = array_values(array_intersect(self::RISK_FEATURES, (array) $snap->serp_features));
                $lost = array_values(array_diff($priorFeatures, $out[$kid]['_latest_features'] ?? []));
                $out[$kid]['features_lost'] = $lost;
                $out[$kid]['lost_feature'] = ! empty($lost);
            }
        }
        foreach ($out as $id => &$entry) {
            unset($entry['_latest_features']);
        }

        return $out;
    }
}
