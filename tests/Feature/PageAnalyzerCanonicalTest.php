<?php

namespace Tests\Feature;

use App\Support\Crawler\PageAnalyzer;
use Tests\TestCase;

class PageAnalyzerCanonicalTest extends TestCase
{
    private function analyze(string $url, string $canonicalHref): array
    {
        $html = '<html><head><title>T</title><link rel="canonical" href="'.$canonicalHref.'"></head><body><h1>x</h1></body></html>';

        return (new PageAnalyzer)->analyze($url, $html);
    }

    public function test_param_url_canonicalling_to_clean_url_is_non_indexable(): void
    {
        $a = $this->analyze('https://example.com/id?name=abc', 'https://example.com/id');
        $this->assertTrue($a['canonical_points_away']);
        $this->assertFalse($a['is_indexable']);
    }

    public function test_self_canonical_with_same_query_is_indexable(): void
    {
        $a = $this->analyze('https://example.com/id?name=abc', 'https://example.com/id?name=abc');
        $this->assertFalse($a['canonical_points_away']);
        $this->assertTrue($a['is_indexable']);
    }

    public function test_trailing_slash_and_www_differences_are_not_pointing_away(): void
    {
        $a = $this->analyze('https://example.com/page', 'https://www.example.com/page/');
        $this->assertFalse($a['canonical_points_away']);
        $this->assertTrue($a['is_indexable']);
    }

    public function test_relative_canonical_to_a_different_path_points_away(): void
    {
        $a = $this->analyze('https://example.com/page', '/other');
        $this->assertTrue($a['canonical_points_away']);
        $this->assertFalse($a['is_indexable']);
    }
}
