<?php

namespace Tests\Unit;

use App\Support\Audit\PageLocalePresentation;
use PHPUnit\Framework\TestCase;

class PageLocalePresentationTest extends TestCase
{
    public function test_should_show_serp_location_false_for_english_hl(): void
    {
        $this->assertFalse(PageLocalePresentation::shouldShowSerpLocationNote(['gl' => 'us', 'hl' => 'en']));
        $this->assertFalse(PageLocalePresentation::shouldShowSerpLocationNote(['gl' => 'gb', 'hl' => 'en-gb']));
    }

    public function test_should_show_serp_location_true_for_non_english(): void
    {
        $this->assertTrue(PageLocalePresentation::shouldShowSerpLocationNote(['gl' => 'jp', 'hl' => 'ja']));
        $this->assertTrue(PageLocalePresentation::shouldShowSerpLocationNote(['gl' => 'fr', 'hl' => 'fr']));
    }

    public function test_should_show_serp_location_false_when_no_line(): void
    {
        $this->assertFalse(PageLocalePresentation::shouldShowSerpLocationNote(null));
        $this->assertFalse(PageLocalePresentation::shouldShowSerpLocationNote([]));
    }

    public function test_short_label_appends_user_chosen_serp_country(): void
    {
        $label = PageLocalePresentation::shortLabel([
            'hl' => 'en',
            'gl' => null,
            'serp_gl_user_chosen' => 'gb',
            'serp_gl_effective' => 'gb',
        ]);
        $this->assertNotNull($label);
        $this->assertStringContainsString('SERP:', $label);
        $this->assertStringContainsString('(gb)', $label);
    }

    public function test_short_label_suppresses_redundant_serp_when_matches_html_gl(): void
    {
        $label = PageLocalePresentation::shortLabel([
            'hl' => 'fr',
            'gl' => 'fr',
            'serp_gl_effective' => 'fr',
        ]);
        $this->assertNotNull($label);
        $this->assertStringNotContainsString('SERP:', $label);
    }
}
