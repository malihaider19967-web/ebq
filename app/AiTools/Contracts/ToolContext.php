<?php

namespace App\AiTools\Contracts;

use App\Models\BrandVoiceProfile;
use App\Models\Website;

/**
 * Per-call context bundle. ContextBuilder populates only the signals
 * the tool's `meta()->contextSignals` opted into; everything else stays
 * null so the tool's prompt builder can short-circuit cheaply.
 *
 * The plugin never sees this — it lives entirely server-side and is
 * the EBQ moat. RankMath has no access to GSC clusters, network-effect
 * patterns, or per-site brand-voice fingerprints.
 */
final class ToolContext
{
    /**
     * @param  list<array{query:string,clicks:int,impressions:int,position:float}>|null  $gscTopQueries
     * @param  array<string, mixed>|null  $gscClustersForKeyword
     * @param  array<string, mixed>|null  $cachedBrief
     * @param  array<string, mixed>|null  $topicalGaps
     * @param  array<string, mixed>|null  $entityCoverage
     * @param  array<string, mixed>|null  $rankSnapshot
     * @param  list<array{url:string,anchor:string,topic:string,clicks:int}>|null  $internalLinkCandidates
     * @param  array<string, mixed>|null  $networkInsight
     * @param  array<string, mixed>|null  $pageAudit
     * @param  array<string, mixed>|null  $seoAnalysis
     */
    public function __construct(
        public readonly Website $website,
        public readonly ?int $userId = null,
        public readonly ?BrandVoiceProfile $brandVoice = null,
        public readonly ?array $gscTopQueries = null,
        public readonly ?array $gscClustersForKeyword = null,
        public readonly ?array $cachedBrief = null,
        public readonly ?array $topicalGaps = null,
        public readonly ?array $entityCoverage = null,
        public readonly ?array $rankSnapshot = null,
        public readonly ?array $internalLinkCandidates = null,
        public readonly ?array $networkInsight = null,
        public readonly ?array $pageAudit = null,
        public readonly ?array $seoAnalysis = null,
        public readonly string $country = 'us',
        public readonly string $language = 'en',
    ) {
    }

    public function locale(): string
    {
        return $this->language . '-' . strtoupper($this->country);
    }
}
