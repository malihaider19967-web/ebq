<?php

namespace App\Services\Research\Niche;

use App\Models\Research\Niche;
use App\Models\Research\NicheKeywordMap;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Collection;

/**
 * Maps a keyword string to one or more niches with relevance scores.
 *
 * Lookup order (Phase-2):
 *   1. Existing niche_keyword_map row (cached classification).
 *   2. Token overlap between the query and any niche slug / name (cheap,
 *      handles the long head: "running shoes" → niche slug "running").
 *   3. LLM fallback for unmatched queries — caches the result so subsequent
 *      lookups for the same query are free.
 *
 * Phase-4 swap: when an EmbeddingProvider is bound and
 * RESEARCH_EMBEDDINGS_ENABLED is on, embedding cosine similarity replaces
 * the LLM fallback path without touching call sites.
 */
class KeywordToNicheMapper
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly ?EmbeddingProvider $embedding = null,
    ) {}

    /**
     * @return Collection<int, array{niche_id:int, relevance_score:float}>
     */
    public function map(string $query, ?int $keywordId = null): Collection
    {
        $cached = $keywordId === null
            ? collect()
            : NicheKeywordMap::query()->where('keyword_id', $keywordId)->get();

        if ($cached->isNotEmpty()) {
            return $cached->map(fn ($row) => [
                'niche_id' => (int) $row->niche_id,
                'relevance_score' => (float) $row->relevance_score,
            ]);
        }

        $tokenMatches = $this->matchByTokens($query);
        if ($tokenMatches->isNotEmpty()) {
            if ($keywordId !== null) {
                $this->cache($keywordId, $tokenMatches);
            }

            return $tokenMatches;
        }

        if ($this->embedding !== null && $this->embedding->isAvailable()) {
            $embeddingMatches = $this->classifyWithEmbeddings($query);
            if ($embeddingMatches->isNotEmpty()) {
                if ($keywordId !== null) {
                    $this->cache($keywordId, $embeddingMatches);
                }

                return $embeddingMatches;
            }
        }

        $llmMatches = $this->classifyWithLlm($query);
        if ($llmMatches->isNotEmpty() && $keywordId !== null) {
            $this->cache($keywordId, $llmMatches);
        }

        return $llmMatches;
    }

    /**
     * @return Collection<int, array{niche_id:int, relevance_score:float}>
     */
    private function classifyWithEmbeddings(string $query): Collection
    {
        $cache = new EmbeddingCache($this->embedding);
        $queryVec = $cache->forText($query);
        if ($queryVec === null) {
            return collect();
        }

        $scored = [];

        Niche::query()
            ->where('is_approved', true)
            ->whereNotNull('parent_id')
            ->chunkById(200, function ($niches) use ($cache, $queryVec, &$scored): void {
                foreach ($niches as $niche) {
                    $vec = $cache->forNiche($niche);
                    if ($vec === null) {
                        continue;
                    }
                    $sim = EmbeddingCache::cosine($queryVec, $vec);
                    if ($sim >= 0.3) {
                        $scored[] = ['niche_id' => $niche->id, 'relevance_score' => round($sim, 4)];
                    }
                }
            });

        usort($scored, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return collect(array_slice($scored, 0, 3));
    }

    /** @return Collection<int, array{niche_id:int, relevance_score:float}> */
    private function matchByTokens(string $query): Collection
    {
        $tokens = preg_split('/[^a-z0-9]+/u', mb_strtolower($query)) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => mb_strlen($t) >= 3));
        if ($tokens === []) {
            return collect();
        }

        $matches = collect();

        Niche::query()->where('is_approved', true)->chunkById(200, function ($niches) use ($tokens, $matches) {
            foreach ($niches as $niche) {
                $haystack = mb_strtolower($niche->slug.' '.$niche->name);
                $hits = 0;
                foreach ($tokens as $token) {
                    if (str_contains($haystack, $token)) {
                        $hits++;
                    }
                }
                if ($hits > 0) {
                    $matches->push([
                        'niche_id' => $niche->id,
                        'relevance_score' => round($hits / count($tokens), 4),
                    ]);
                }
            }
        });

        return $matches->sortByDesc('relevance_score')->take(3)->values();
    }

    /** @return Collection<int, array{niche_id:int, relevance_score:float}> */
    private function classifyWithLlm(string $query): Collection
    {
        if (! $this->llm->isAvailable()) {
            return collect();
        }

        $catalogue = Niche::query()
            ->where('is_approved', true)
            ->whereNotNull('parent_id')
            ->get(['id', 'slug', 'name'])
            ->map(fn ($n) => "- {$n->slug} ({$n->name})")
            ->implode("\n");

        $messages = [
            ['role' => 'system', 'content' => 'You map a search query to up to 3 niches from a catalogue. Reply with strict JSON {"matches":[{"slug":"...","relevance":0.0..1.0}]}. Use only slugs from the catalogue.'],
            ['role' => 'user', 'content' => "Catalogue:\n{$catalogue}\n\nQuery: {$query}"],
        ];

        $decoded = $this->llm->completeJson($messages, ['temperature' => 0.0, 'max_tokens' => 256]);
        if (! is_array($decoded) || ! isset($decoded['matches']) || ! is_array($decoded['matches'])) {
            return collect();
        }

        $bySlug = Niche::query()
            ->whereIn('slug', array_filter(array_map(fn ($m) => is_array($m) ? (string) ($m['slug'] ?? '') : '', $decoded['matches'])))
            ->pluck('id', 'slug');

        $out = collect();
        foreach ($decoded['matches'] as $match) {
            if (! is_array($match)) {
                continue;
            }
            $slug = (string) ($match['slug'] ?? '');
            $relevance = (float) ($match['relevance'] ?? 0.0);
            if ($slug !== '' && isset($bySlug[$slug]) && $relevance > 0) {
                $out->push([
                    'niche_id' => (int) $bySlug[$slug],
                    'relevance_score' => round(max(0.0, min(1.0, $relevance)), 4),
                ]);
            }
        }

        return $out;
    }

    /** @param Collection<int, array{niche_id:int, relevance_score:float}> $matches */
    private function cache(int $keywordId, Collection $matches): void
    {
        foreach ($matches as $match) {
            NicheKeywordMap::query()->updateOrInsert(
                ['niche_id' => $match['niche_id'], 'keyword_id' => $keywordId],
                ['relevance_score' => $match['relevance_score'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
