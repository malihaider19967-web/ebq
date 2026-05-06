<?php

namespace Tests\Feature\Research;

use App\Services\Research\Niche\EmbeddingProvider;
use App\Services\Research\Niche\MistralEmbeddingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbeddingProviderBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_is_unbound_by_default(): void
    {
        config()->set('research.embeddings.enabled', false);
        config()->set('services.mistral.key', 'test-key');

        // Force re-register so the conditional binding re-evaluates with the new config.
        $this->refreshApplication();
        config()->set('research.embeddings.enabled', false);
        config()->set('services.mistral.key', 'test-key');

        $this->assertFalse(app()->bound(EmbeddingProvider::class), 'EmbeddingProvider should be unbound when env flag is off — KeywordToNicheMapper falls back to rule-based.');
    }

    public function test_provider_is_unbound_when_mistral_key_is_empty(): void
    {
        config()->set('research.embeddings.enabled', true);
        config()->set('services.mistral.key', '');
        $this->refreshApplication();
        config()->set('research.embeddings.enabled', true);
        config()->set('services.mistral.key', '');

        $this->assertFalse(app()->bound(EmbeddingProvider::class));
    }

    public function test_mistral_provider_returns_empty_vectors_when_unconfigured(): void
    {
        $provider = new MistralEmbeddingProvider('');

        $this->assertFalse($provider->isAvailable());
        $vectors = $provider->embed(['hello']);
        $this->assertCount(1, $vectors);
        $this->assertSame([], $vectors[0]);
    }
}
