<?php

namespace Tests\Unit;

use App\Support\Audit\PageLocaleResolver;
use PHPUnit\Framework\TestCase;

class PageLocaleResolverTest extends TestCase
{
    public function test_hreflang_self_match_wins_over_og_locale(): void
    {
        $page = 'https://shop.example.com/fr/produit';
        $signals = [
            'html_lang' => 'en',
            'og_locale' => 'en_US',
            'hreflangs' => [
                ['hreflang' => 'fr-FR', 'href' => 'https://shop.example.com/fr/produit'],
                ['hreflang' => 'en-US', 'href' => 'https://shop.example.com/en/product'],
            ],
        ];
        $out = PageLocaleResolver::resolve($signals, $page);
        $this->assertSame('hreflang_self', $out['source']);
        $this->assertSame('fr', $out['hl']);
        $this->assertSame('fr', $out['gl']);
    }

    public function test_og_locale_used_when_no_hreflang_match(): void
    {
        $signals = [
            'html_lang' => null,
            'og_locale' => 'it_IT',
            'hreflangs' => [],
        ];
        $out = PageLocaleResolver::resolve($signals, 'https://x.test/');
        $this->assertSame('og_locale', $out['source']);
        $this->assertSame('it', $out['hl']);
        $this->assertSame('it', $out['gl']);
    }

    public function test_html_lang_only_sets_hl_without_region(): void
    {
        $signals = [
            'html_lang' => 'de',
            'og_locale' => null,
            'hreflangs' => [],
        ];
        $out = PageLocaleResolver::resolve($signals, 'https://x.test/');
        $this->assertSame('html_lang', $out['source']);
        $this->assertSame('de', $out['hl']);
        $this->assertNull($out['gl']);
    }
}
