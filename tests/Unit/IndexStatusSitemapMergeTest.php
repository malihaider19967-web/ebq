<?php

namespace Tests\Unit;

use App\Models\PageIndexingStatus;
use App\Services\PluginInsightResolver;
use App\Support\UrlNormalizer;
use Illuminate\Support\Collection;
use Tests\TestCase;

class IndexStatusSitemapMergeTest extends TestCase
{
    public function test_sitemap_urls_merge_without_overwriting_gsc_or_pis_canonical(): void
    {
        $pis = new PageIndexingStatus([
            'page' => 'https://example.com/from-inspection',
        ]);
        $pis->setAttribute('id', 1);

        $resolver = app(PluginInsightResolver::class);
        $merged = $resolver->dedupePageUrlsForIndexStatus(
            ['https://example.com/from-gsc'],
            collect([UrlNormalizer::normalize('https://example.com/from-inspection') => $pis]),
            ['https://www.example.com/from-sitemap/'],
        );

        $norms = array_map(fn (string $u) => UrlNormalizer::normalize($u), $merged);
        $this->assertCount(3, array_unique($norms));
        $this->assertContains('https://example.com/from-inspection', $merged);
        $this->assertContains('https://example.com/from-gsc', $merged);
        $this->assertContains('https://www.example.com/from-sitemap/', $merged);
    }

    public function test_sitemap_url_collapses_when_gsc_already_has_normalized_match(): void
    {
        $resolver = app(PluginInsightResolver::class);
        $merged = $resolver->dedupePageUrlsForIndexStatus(
            ['https://example.com/blog/post'],
            collect(),
            ['https://example.com/blog/post/'],
        );

        $this->assertCount(1, $merged);
        $this->assertSame('https://example.com/blog/post', $merged[0]);
    }
}
