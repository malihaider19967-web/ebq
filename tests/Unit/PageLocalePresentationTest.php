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
}
