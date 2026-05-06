<?php

namespace App\Services\Research;

use App\Models\Website;
use App\Services\Ai\ContextBuilder;
use App\Services\NetworkInsightService;
use App\Services\SerperSearchClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for the "editor research" bundle. Extracted from
 * PluginInsightsController::research so the WP plugin endpoint and the
 * portal's Research section can share one definition + one cache shape.
 *
 *   Cache key: editor_research:v1:{website_id}:{kw_hash}:{country}
 *   TTL:       30 min
 *
 * Entity coverage stays out of the cache — it's an opt-in expensive LLM
 * call. Callers that need it should run EntityCoverageService directly
 * after fetching the bundle, exactly like the legacy plugin endpoint did.
 */
class ResearchAggregateService
{
    public const CACHE_KEY_PREFIX = 'editor_research:v1';
    public const CACHE_TTL_SECONDS = 1800;

    public function __construct(
        private readonly SerperSearchClient $serper,
        private readonly NetworkInsightService $networkInsight,
        private readonly ContextBuilder $contextBuilder,
    ) {}

    public function cacheKey(int $websiteId, string $keyword, string $country): string
    {
        return sprintf(
            '%s:%d:%s:%s',
            self::CACHE_KEY_PREFIX,
            $websiteId,
            hash('xxh3', mb_strtolower($keyword)),
            $country
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function bundle(
        Website $website,
        string $focusKeyword,
        string $country = 'us',
        string $language = 'en',
        string $url = '',
    ): array {
        $kw = trim($focusKeyword);
        if ($kw === '') {
            return [];
        }

        return Cache::remember(
            $this->cacheKey($website->id, $kw, $country),
            self::CACHE_TTL_SECONDS,
            fn () => $this->build($website, $kw, $country, $language, $url),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Website $website, string $kw, string $country, string $language, string $url): array
    {
        $serpRes = $this->serper->query([
            'q' => $kw,
            'type' => 'organic',
            'num' => 10,
            'gl' => $country,
            'hl' => $language,
            '__website_id' => $website->id,
            '__owner_user_id' => $website->user_id,
        ]);

        $organic = is_array($serpRes['organic'] ?? null) ? $serpRes['organic'] : [];
        $serpTop = [];
        foreach (array_slice($organic, 0, 5) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $serpTop[] = [
                'position' => (int) ($row['position'] ?? ($i + 1)),
                'title' => (string) ($row['title'] ?? ''),
                'snippet' => (string) ($row['snippet'] ?? ''),
                'url' => (string) ($row['link'] ?? ''),
                'displayed_link' => (string) ($row['displayedLink'] ?? ''),
            ];
        }

        $paa = [];
        foreach ((array) ($serpRes['peopleAlsoAsk'] ?? []) as $item) {
            if (is_string($item)) {
                $paa[] = $item;
            } elseif (is_array($item) && is_string($item['question'] ?? null)) {
                $paa[] = (string) $item['question'];
            }
        }

        $related = [];
        foreach ((array) ($serpRes['relatedSearches'] ?? []) as $item) {
            if (is_string($item)) {
                $related[] = $item;
            } elseif (is_array($item) && is_string($item['query'] ?? null)) {
                $related[] = (string) $item['query'];
            }
        }

        $internalLinks = $this->contextBuilder->loadInternalLinkCandidatesPublic($website, $kw, $url);
        $network = $this->networkInsight->forKeyword($kw, $country);

        return [
            'focus_keyword' => $kw,
            'country' => $country,
            'language' => $language,
            'serp_top' => $serpTop,
            'people_also_ask' => array_values(array_unique($paa)),
            'related_searches' => array_values(array_unique($related)),
            'keyword_suggestions' => array_slice(array_values(array_unique($related)), 0, 12),
            'internal_link_candidates' => is_array($internalLinks) ? $internalLinks : [],
            'network_insight' => $network,
            'cached_at' => Carbon::now()->toIso8601String(),
        ];
    }
}
