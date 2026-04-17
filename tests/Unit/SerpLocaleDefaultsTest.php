<?php

namespace Tests\Unit;

use App\Support\Audit\SerpLocaleDefaults;
use PHPUnit\Framework\TestCase;

class SerpLocaleDefaultsTest extends TestCase
{
    public function test_explicit_gl_wins(): void
    {
        $out = SerpLocaleDefaults::forSerperRequest('de', 'en', null);
        $this->assertSame('de', $out['gl']);
        $this->assertSame('en', $out['hl']);
    }

    public function test_invalid_gl_falls_back_to_inference(): void
    {
        $out = SerpLocaleDefaults::forSerperRequest('xxz', 'ja', null);
        $this->assertSame('jp', $out['gl']);
        $this->assertSame('ja', $out['hl']);
    }

    public function test_ja_without_gl_defaults_jp(): void
    {
        $out = SerpLocaleDefaults::forSerperRequest(null, 'ja', null);
        $this->assertSame('jp', $out['gl']);
        $this->assertSame('ja', $out['hl']);
    }

    public function test_en_without_gl_defaults_us(): void
    {
        $out = SerpLocaleDefaults::forSerperRequest(null, 'en', null);
        $this->assertSame('us', $out['gl']);
        $this->assertSame('en', $out['hl']);
    }

    public function test_en_gb_uses_region_from_hl(): void
    {
        $out = SerpLocaleDefaults::forSerperRequest(null, 'en-gb', null);
        $this->assertSame('gb', $out['gl']);
        $this->assertSame('en-gb', $out['hl']);
    }

    public function test_zh_hans_defaults_cn(): void
    {
        $out = SerpLocaleDefaults::forSerperRequest(null, 'zh-hans', 'zh-Hans');
        $this->assertSame('cn', $out['gl']);
        $this->assertSame('zh-hans', $out['hl']);
    }

    public function test_zh_tw_from_bcp47(): void
    {
        $out = SerpLocaleDefaults::forSerperRequest(null, 'zh', 'zh-TW');
        $this->assertSame('tw', $out['gl']);
        $this->assertSame('zh', $out['hl']);
    }

    public function test_zh_hant_defaults_tw(): void
    {
        $out = SerpLocaleDefaults::forSerperRequest(null, 'zh-hant', null);
        $this->assertSame('tw', $out['gl']);
    }

    public function test_null_hl_yields_null_gl(): void
    {
        $out = SerpLocaleDefaults::forSerperRequest(null, null, null);
        $this->assertNull($out['gl']);
        $this->assertNull($out['hl']);
    }

    public function test_is_valid_serper_gl(): void
    {
        $this->assertTrue(SerpLocaleDefaults::isValidSerperGl('FR'));
        $this->assertFalse(SerpLocaleDefaults::isValidSerperGl('FRA'));
        $this->assertFalse(SerpLocaleDefaults::isValidSerperGl(''));
    }
}
