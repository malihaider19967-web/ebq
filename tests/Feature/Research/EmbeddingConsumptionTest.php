<?php

namespace Tests\Feature\Research;

use App\Models\Research\Keyword;
use App\Models\Research\KeywordCluster;
use App\Models\Research\Niche;
use App\Services\Research\ClusteringService;
use App\Services\Research\Niche\EmbeddingCache;
use App\Services\Research\Niche\EmbeddingProvider;
use App\Services\Research\Niche\KeywordToNicheMapper;
use Database\Seeders\NicheTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stub provider returning deterministic vectors keyed off the input
 * tokens — lets us write assertions without a real Mistral round-trip.
 * Each text becomes a one-hot-ish vector based on hashed tokens.
 */
class StubEmbeddingProvider implements EmbeddingProvider
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function embed(array $texts): array
    {
        $out = [];
        foreach ($texts as $text) {
            $vec = array_fill(0, 32, 0.0);
            $tokens = preg_split('/[^a-z0-9]+/u', mb_strtolower((string) $text)) ?: [];
            foreach (array_filter($tokens) as $token) {
                $bucket = abs(crc32($token)) % 32;
                $vec[$bucket] += 1.0;
            }
            $out[] = $vec;
        }

        return $out;
    }
}

class EmbeddingConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cosine_returns_one_for_identical_and_zero_for_orthogonal(): void
    {
        $a = [1.0, 0.0, 0.0];
        $b = [1.0, 0.0, 0.0];
        $c = [0.0, 1.0, 0.0];

        $this->assertEqualsWithDelta(1.0, EmbeddingCache::cosine($a, $b), 0.0001);
        $this->assertEqualsWithDelta(0.0, EmbeddingCache::cosine($a, $c), 0.0001);
    }

    public function test_keyword_embedding_is_persisted_to_blob_column(): void
    {
        $kw = Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor('blue suede shoes'), 'country' => 'us', 'language' => 'en'],
            ['query' => 'blue suede shoes', 'normalized_query' => 'blue suede shoes']
        );

        $cache = new EmbeddingCache(new StubEmbeddingProvider());
        $vec = $cache->forKeyword($kw);

        $this->assertNotNull($vec);
        $this->assertCount(32, $vec);
        $kw->refresh();
        $this->assertNotNull($kw->embedding);

        // Second call should hit the cache (vector is unchanged even if
        // the provider were to return something different).
        $again = $cache->forKeyword($kw);
        $this->assertSame($vec, $again);
    }

    public function test_mapper_falls_back_to_embeddings_when_token_match_fails(): void
    {
        (new NicheTaxonomySeeder())->run();

        $llm = new class implements \App\Services\Llm\LlmClient {
            public function complete(array $messages, array $options = []): array { return ['ok' => false, 'content' => '', 'model' => '', 'usage' => ['prompt' => 0, 'completion' => 0, 'total' => 0]]; }
            public function completeJson(array $messages, array $options = []): ?array { return null; }
            public function isAvailable(): bool { return false; }
            public function completeWithTools(array $messages, array $tools, callable $dispatcher, array $options = []): array { return ['ok' => false, 'decoded' => null, 'content' => '', 'model' => '', 'usage' => ['prompt' => 0, 'completion' => 0, 'total' => 0], 'tool_calls' => []]; }
        };

        $mapper = new KeywordToNicheMapper($llm, new StubEmbeddingProvider());

        // A query with no obvious token overlap with any niche slug/name.
        $matches = $mapper->map('xyzzy plugh quux');

        // Without embeddings we'd get nothing here (no token match, no LLM).
        // With embeddings we should get at least one match because the stub
        // produces non-zero vectors for any non-empty input.
        $this->assertGreaterThan(0, $matches->count(), 'Embedding fallback should produce at least one niche match.');
    }

    public function test_clustering_service_uses_embeddings_when_provider_is_set(): void
    {
        $a = Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor('crispy fried chicken recipe'), 'country' => 'us', 'language' => 'en'],
            ['query' => 'crispy fried chicken recipe', 'normalized_query' => 'crispy fried chicken recipe']
        );
        $b = Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor('fried chicken crispy recipe easy'), 'country' => 'us', 'language' => 'en'],
            ['query' => 'fried chicken crispy recipe easy', 'normalized_query' => 'fried chicken crispy recipe easy']
        );

        $service = new ClusteringService(
            similarityThreshold: 0.4,
            embedding: new StubEmbeddingProvider(),
            embeddingThreshold: 0.6,
        );

        $clusters = $service->cluster([$a, $b]);

        $this->assertSame(1, $clusters->count(), 'Two near-identical keyword phrases should land in one embedding cluster.');
        $this->assertSame('embedding', $clusters->first()->signal);
    }
}
