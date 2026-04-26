<?php

namespace App\Services;

use App\Models\PageAuditReport;
use App\Models\Website;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 #11 — Entity coverage analyzer (E-E-A-T signal).
 *
 * Approach (pragmatic, no Wikidata API):
 *   For a given URL, we already have:
 *     - The page's body text (in the audit's `result.content.body_text`)
 *     - The top-3 SERP competitor titles + snippets (in `result.benchmark`)
 *
 *   We send both to the LLM and ask it to:
 *     1. Extract entities (people, brands, products, concepts) the page
 *        explicitly mentions.
 *     2. Extract entities the COMPETITOR top-3 explicitly mention.
 *     3. Diff → "expected entities for this topic that you don't mention".
 *
 *   Output reads like a Wikidata-style coverage report ("you cover Apple,
 *   Google; competitors also cover Microsoft, OpenAI, Anthropic — adding
 *   Microsoft would close the biggest gap") without requiring SPARQL or a
 *   Google KG API key.
 *
 *   When a richer Wikidata pass becomes worthwhile, the same output shape
 *   can be enriched with `wikidata_id` + `description` per entity — no
 *   change to the consumer.
 *
 * Caching: 7d per (post × content-hash). Entity coverage doesn't shift
 * meaningfully without content edits.
 *
 * MOAT
 * ────
 * The diff against competitor entities requires: (a) the audit pipeline
 * (server-side), (b) the SERP benchmark (Serper credits), (c) the LLM
 * extraction. Plugin can't reproduce any of the three offline.
 */
class EntityCoverageService
{
    private const CACHE_TTL_DAYS = 7;
    private const MAX_BODY_CHARS = 5000;

    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @return array{
     *   ok: bool,
     *   reason?: string,
     *   yours: list<string>,
     *   competitors: list<string>,
     *   missing: list<array{entity: string, type: string, why: string}>,
     *   cached?: bool,
     * }
     */
    public function analyze(Website $website, string $url): array
    {
        if (! $this->llm->isAvailable()) {
            return $this->unavailable('llm_not_configured');
        }

        $audit = PageAuditReport::query()
            ->where('website_id', $website->id)
            ->where('page_hash', hash('sha256', $url))
            ->where('status', 'completed')
            ->latest('audited_at')
            ->first();

        if (! $audit || ! is_array($audit->result)) {
            return $this->unavailable('no_audit');
        }

        $body = (string) data_get($audit->result, 'content.body_text', '');
        if ($body === '') {
            return $this->unavailable('no_body_text');
        }
        $body = mb_substr($body, 0, self::MAX_BODY_CHARS);

        $competitors = data_get($audit->result, 'benchmark.competitors', []);
        $competitorBlock = '';
        if (is_array($competitors) && $competitors !== []) {
            foreach (array_slice($competitors, 0, 3) as $i => $c) {
                if (! is_array($c)) continue;
                $competitorBlock .= sprintf(
                    "Competitor %d: %s\nURL: %s\n\n",
                    $i + 1,
                    (string) ($c['title'] ?? ''),
                    (string) ($c['url'] ?? ''),
                );
            }
        }
        if ($competitorBlock === '') {
            $competitorBlock = '(no competitor data — entity diff will be one-sided)';
        }

        $cacheKey = sprintf('ebq_entity_coverage:%s', hash('xxh3', $url . '|' . $body));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $cached['cached'] = true;
            return $cached;
        }

        $messages = [
            ['role' => 'system', 'content' => 'You extract named entities from web content for SEO E-E-A-T analysis. Entities = people, brands, products, organizations, places, frameworks, concepts that a topic-savvy reader would expect to see mentioned. NOT generic nouns. Return strict JSON.'],
            ['role' => 'user', 'content' => sprintf(
                "URL being audited: %s\n\nUser's page body (first %d chars):\n---\n%s\n---\n\nTop-ranking competitor titles:\n%s\n\nReturn JSON in this shape:\n{\n  \"yours\": [\"...\"],\n  \"competitors\": [\"...\"],\n  \"missing\": [\n    {\"entity\": \"...\", \"type\": \"person|brand|product|org|place|framework|concept\", \"why\": \"One sentence: why this entity matters for the topic.\"}\n  ]\n}\n\nRules:\n- Up to 15 entities per side.\n- 'missing' = entities the competitors mention but the user's page doesn't (max 8).\n- Prioritize entities with strongest topical authority impact.",
                $url,
                self::MAX_BODY_CHARS,
                $body,
                $competitorBlock,
            )],
        ];

        $decoded = $this->llm->completeJson($messages, [
            'temperature' => 0.2,
            'max_tokens' => 1400,
            'timeout' => 28,
        ]);

        if (! is_array($decoded)) {
            Log::warning('EntityCoverageService: llm parse failed', ['url' => $url]);
            return $this->unavailable('llm_parse_failed');
        }

        $result = [
            'ok' => true,
            'yours' => $this->cleanList($decoded['yours'] ?? [], 15),
            'competitors' => $this->cleanList($decoded['competitors'] ?? [], 15),
            'missing' => $this->cleanMissing($decoded['missing'] ?? []),
            'cached' => false,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
        Cache::put($cacheKey, $result, Carbon::now()->addDays(self::CACHE_TTL_DAYS));
        return $result;
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function cleanList($raw, int $cap): array
    {
        if (! is_array($raw)) return [];
        $out = [];
        foreach ($raw as $v) {
            if (! is_string($v)) continue;
            $s = trim($v);
            if ($s === '') continue;
            $out[mb_strtolower($s)] = mb_substr($s, 0, 80);
            if (count($out) >= $cap) break;
        }
        return array_values($out);
    }

    /**
     * @param  mixed  $raw
     * @return list<array{entity: string, type: string, why: string}>
     */
    private function cleanMissing($raw): array
    {
        if (! is_array($raw)) return [];
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) continue;
            $entity = trim((string) ($row['entity'] ?? ''));
            if ($entity === '') continue;
            $out[] = [
                'entity' => mb_substr($entity, 0, 80),
                'type' => mb_substr(trim((string) ($row['type'] ?? 'concept')), 0, 16),
                'why' => mb_substr(trim((string) ($row['why'] ?? '')), 0, 200),
            ];
            if (count($out) >= 8) break;
        }
        return $out;
    }

    private function unavailable(string $reason): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'yours' => [],
            'competitors' => [],
            'missing' => [],
        ];
    }
}
