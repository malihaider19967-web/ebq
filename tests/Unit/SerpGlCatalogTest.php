<?php

namespace Tests\Unit;

use App\Support\Audit\SerpGlCatalog;
use PHPUnit\Framework\TestCase;

class SerpGlCatalogTest extends TestCase
{
    public function test_select_options_has_many_territories(): void
    {
        $opts = SerpGlCatalog::selectOptions();
        $this->assertGreaterThan(100, count($opts));
        $this->assertArrayHasKey('us', $opts);
        $this->assertArrayHasKey('jp', $opts);
    }

    public function test_label_for_known_code(): void
    {
        $this->assertStringContainsString('United States', SerpGlCatalog::labelFor('us'));
    }

    public function test_is_allowed_gl_matches_serper_rules(): void
    {
        $this->assertTrue(SerpGlCatalog::isAllowedGl('fr'));
        $this->assertFalse(SerpGlCatalog::isAllowedGl('zzz'));
    }
}
