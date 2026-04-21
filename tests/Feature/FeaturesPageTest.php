<?php

namespace Tests\Feature;

use Tests\TestCase;

class FeaturesPageTest extends TestCase
{
    public function test_features_page_is_public(): void
    {
        $this->get(route('features'))
            ->assertOk()
            ->assertSee('Ship SEO decisions, not dashboards')
            ->assertSee('Cross-signal insights')
            ->assertSee('Rank tracking')
            ->assertSee('Anomaly alerts')
            ->assertSee('Page audits')
            ->assertSee('Backlinks')
            ->assertSee('Reporting')
            ->assertSee('Team &amp; permissions', escape: false)
            ->assertSee('Integrations');
    }

    public function test_landing_nav_links_to_features_page(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee(route('features'));
    }
}
