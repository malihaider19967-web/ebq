<?php

namespace Tests\Unit;

use App\Support\Audit\SerpGlCountryPrompt;
use PHPUnit\Framework\TestCase;

class SerpGlCountryPromptTest extends TestCase
{
    public function test_recommended_gl_japanese_defaults_jp(): void
    {
        $this->assertSame('jp', SerpGlCountryPrompt::recommendedGl(null, 'ja', null));
    }

    public function test_recommended_gl_respects_html_region(): void
    {
        $this->assertSame('de', SerpGlCountryPrompt::recommendedGl('de', 'de', null));
    }

    public function test_recommendation_hint_mentions_gl(): void
    {
        $hint = SerpGlCountryPrompt::recommendationHint('jp', null, 'ja', null);
        $this->assertStringContainsString('gl=jp', $hint);
        $this->assertStringContainsString('Japan', $hint);
    }
}
