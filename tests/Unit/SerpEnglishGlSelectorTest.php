<?php

namespace Tests\Unit;

use App\Support\Audit\SerpEnglishGlSelector;
use PHPUnit\Framework\TestCase;

class SerpEnglishGlSelectorTest extends TestCase
{
    public function test_needs_choice_when_english_and_no_gl(): void
    {
        $this->assertTrue(SerpEnglishGlSelector::needsEnglishSerpCountryChoice('en', null));
        $this->assertTrue(SerpEnglishGlSelector::needsEnglishSerpCountryChoice('en', ''));
    }

    public function test_no_choice_when_english_with_gl(): void
    {
        $this->assertFalse(SerpEnglishGlSelector::needsEnglishSerpCountryChoice('en', 'us'));
        $this->assertFalse(SerpEnglishGlSelector::needsEnglishSerpCountryChoice('en', 'gb'));
    }

    public function test_no_choice_for_non_english(): void
    {
        $this->assertFalse(SerpEnglishGlSelector::needsEnglishSerpCountryChoice('ja', null));
        $this->assertFalse(SerpEnglishGlSelector::needsEnglishSerpCountryChoice('fr', null));
    }

    public function test_is_allowed_gl(): void
    {
        $this->assertTrue(SerpEnglishGlSelector::isAllowedGl('gb'));
        $this->assertTrue(SerpEnglishGlSelector::isAllowedGl('US'));
        $this->assertFalse(SerpEnglishGlSelector::isAllowedGl('xx'));
        $this->assertFalse(SerpEnglishGlSelector::isAllowedGl('de'));
    }

    public function test_select_options_non_empty(): void
    {
        $this->assertNotEmpty(SerpEnglishGlSelector::selectOptions());
        $this->assertArrayHasKey('us', SerpEnglishGlSelector::selectOptions());
    }
}
