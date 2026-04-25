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

    public function test_landing_advertises_wordpress_plugin_behind_sign_in(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('WordPress plugin')
            ->assertSee('Sign in to download plugin')
            ->assertDontSee('/wordpress/plugin.zip', escape: false);
    }

    public function test_features_page_has_wordpress_plugin_section(): void
    {
        $this->get(route('features'))
            ->assertOk()
            ->assertSee('Ship insights where editors already work')
            ->assertDontSee('/wordpress/plugin.zip', escape: false);
    }

    public function test_plugin_zip_is_publicly_accessible(): void
    {
        $path = public_path('downloads/ebq-seo.zip');
        $this->assertFileExists($path);
        $this->assertGreaterThan(5_000, filesize($path), 'Plugin zip looks empty.');
    }

    public function test_plugin_version_endpoint_reflects_source_header(): void
    {
        $response = $this->getJson(route('wordpress.plugin.version'));

        $response->assertOk()
            ->assertJsonStructure([
                'slug',
                'name',
                'version',
                'download_url',
                'requires' => ['wp', 'php'],
                'tested',
                'homepage',
            ]);

        $this->assertSame('ebq-seo', $response->json('slug'));
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', (string) $response->json('version'));
        $this->assertStringContainsString('/wordpress/plugin.zip', (string) $response->json('download_url'));
    }

    // TEMP: plugin downloads are disabled — restore the original assertions when re-enabling.
    public function test_plugin_download_route_is_temporarily_disabled(): void
    {
        $this->get(route('wordpress.plugin.download'))->assertStatus(503);
    }
}
