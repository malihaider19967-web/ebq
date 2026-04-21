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

    public function test_landing_advertises_wordpress_plugin_and_download(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('WordPress plugin')
            ->assertSee('downloads/ebq-seo.zip', escape: false);
    }

    public function test_features_page_has_wordpress_plugin_section(): void
    {
        $this->get(route('features'))
            ->assertOk()
            ->assertSee('Ship insights where editors already work')
            ->assertSee('downloads/ebq-seo.zip', escape: false);
    }

    public function test_plugin_zip_is_publicly_accessible(): void
    {
        $path = public_path('downloads/ebq-seo.zip');
        $this->assertFileExists($path);
        $this->assertGreaterThan(5_000, filesize($path), 'Plugin zip looks empty.');
    }
}
