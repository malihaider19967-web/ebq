<?php

namespace Tests\Unit;

use App\Models\PageIndexingStatus;
use App\Models\User;
use App\Models\Website;
use App\Services\PluginInsightResolver;
use App\Support\UrlNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveStoredPageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_resolves_existing_indexing_row_with_trailing_slash_variant(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create([
            'user_id' => $user->id,
            'domain' => 'example.com',
        ]);

        PageIndexingStatus::query()->create([
            'website_id' => $website->id,
            'page' => UrlNormalizer::normalize('https://example.com/about'),
        ]);

        $resolver = app(PluginInsightResolver::class);
        $stored = $resolver->resolveStoredPageUrl($website, 'https://www.example.com/about/');

        $this->assertSame('https://example.com/about', $stored);
        $this->assertSame(1, PageIndexingStatus::query()->where('website_id', $website->id)->count());
    }

    public function test_submit_merges_duplicate_indexing_rows_on_resolve(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create([
            'user_id' => $user->id,
            'domain' => 'example.com',
        ]);

        PageIndexingStatus::query()->create([
            'website_id' => $website->id,
            'page' => 'https://example.com/contact',
            'last_reindex_requested_at' => now()->subDay(),
        ]);
        PageIndexingStatus::query()->create([
            'website_id' => $website->id,
            'page' => 'https://example.com/contact/',
            'last_google_status_checked_at' => now(),
        ]);

        $resolver = app(PluginInsightResolver::class);
        $stored = $resolver->resolveStoredPageUrl($website, 'https://example.com/contact/');

        $this->assertSame('https://example.com/contact/', $stored);
        $this->assertSame(1, PageIndexingStatus::query()->where('website_id', $website->id)->count());
    }
}
