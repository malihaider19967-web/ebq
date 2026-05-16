<?php

namespace App\Services\Research;

use App\Models\Research\Keyword;
use App\Models\Research\KeywordIntelligence;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use App\Models\Website;
use App\Services\KeywordsEverywhereClient;
use App\Services\Llm\LlmClient;
use App\Services\Research\Intelligence\KeywordDifficultyEngine;
use App\Services\Research\Quota\ResearchCostLogger;
use App\Services\Research\Quota\ResearchQuotaService;
use Illuminate\Support\Carbon;

/**
 * Pipeline 4 — fills `keyword_intelligence` for a Keyword:
 *   - search_volume / cpc / competition  ← KeywordsEverywhereClient
 *   - intent                              ← LLM classification (cached per keyword)
 *   - difficulty_score / serp_strength    ← KeywordDifficultyEngine over the
 *                                           latest SerpSnapshot
 */
class KeywordEnrichmentService
{
    public function __construct(
        private readonly KeywordsEverywhereClient $ke,
        private readonly LlmClient $llm,
        private readonly KeywordDifficultyEngine $difficulty,
        private readonly ResearchQuotaService $quota,
        private readonly ResearchCostLogger $cost,
    ) {}

    public function enrich(Keyword $keyword, ?Website $website = null): KeywordIntelligence
    {
        $intel = KeywordIntelligence::firstOrNew(['keyword_id' => $keyword->id]);

        $this->maybeFetchVolume($keyword, $intel, $website);
        $this->maybeClassifyIntent($keyword, $intel, $website);
        $this->maybeScoreDifficulty($keyword, $intel);

        $intel->save();

        return $intel;
    }

    private function maybeFetchVolume(Keyword $keyword, KeywordIntelligence $intel, ?Website $website): void
    {
        // Operator gate. KE bills 1 credit per keyword per request; the
        // pipeline enrichment path can blow through credits fast on a
        // freshly-scraped corpus. Read live from the admin settings so
        // the operator can flip without redeploying. Manual
        // /research/keywords lookups still go through their own path
        // and aren't gated by this flag.
        if (! \App\Support\ResearchEngineSettings::autoFetchVolume()) {
            return;
        }

        if ($intel->last_metrics_at !== null && $intel->last_metrics_at->gt(Carbon::now()->subDays(30))) {
            return;
        }

        $this->quota->assertCanSpend($website, 'keyword_lookup', 1);

        $resp = $this->ke->getKeywordData(
            keywords: [$keyword->query],
            country: $keyword->country !== 'global' ? $keyword->country : 'global',
            ownerUserId: null,
            websiteId: $website?->id,
            source: 'research',
        );

        if (! is_array($resp) || ! isset($resp['data'][0])) {
            return;
        }

        $row = $resp['data'][0];

        $intel->search_volume = isset($row['vol']) ? (int) $row['vol'] : $intel->search_volume;
        $intel->cpc = isset($row['cpc']['value']) ? (float) $row['cpc']['value'] : $intel->cpc;
        $intel->competition = isset($row['competition']) ? (float) $row['competition'] : $intel->competition;
        $intel->last_metrics_at = Carbon::now();

        $this->cost->log('keyword_lookup', $website?->id, 'keywords_everywhere', [
            'keyword_id' => $keyword->id,
        ], 1);
    }

    private function maybeClassifyIntent(Keyword $keyword, KeywordIntelligence $intel, ?Website $website): void
    {
        if ($intel->intent !== null) {
            return;
        }

        if (! $this->llm->isAvailable()) {
            return;
        }

        $this->quota->assertCanSpend($website, 'llm_call', 1);

        $messages = [
            ['role' => 'system', 'content' => 'You classify a single search query into one search-intent bucket. Reply with strict JSON: {"intent":"informational|transactional|commercial|navigational"}. No extra text.'],
            ['role' => 'user', 'content' => 'Query: '.$keyword->query],
        ];

        $decoded = $this->llm->completeJson($messages, ['temperature' => 0.0, 'max_tokens' => 16]);
        $intent = is_array($decoded) ? (string) ($decoded['intent'] ?? '') : '';

        if (in_array($intent, ['informational', 'transactional', 'commercial', 'navigational'], true)) {
            $intel->intent = $intent;
            $this->cost->log('llm_call', $website?->id, 'mistral', [
                'operation' => 'intent_classification',
                'keyword_id' => $keyword->id,
            ], 1);
        }
    }

    private function maybeScoreDifficulty(Keyword $keyword, KeywordIntelligence $intel): void
    {
        $snapshot = SerpSnapshot::query()
            ->where('keyword_id', $keyword->id)
            ->orderByDesc('fetched_at')
            ->first();

        if ($snapshot === null) {
            return;
        }

        /** @var \Illuminate\Support\Collection<int, SerpResult> $results */
        $results = $snapshot->results()->orderBy('rank')->limit(10)->get();
        if ($results->isEmpty()) {
            return;
        }

        $score = $this->difficulty->score($results->all());
        $intel->difficulty_score = $score['difficulty'];
        $intel->serp_strength_score = $score['serp_strength'];
        $intel->last_serp_at = $snapshot->fetched_at;
    }
}
