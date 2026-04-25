<?php

namespace Tests\Feature;

use App\Models\PluginRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginReleaseEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_endpoint_prefers_published_release_by_channel(): void
    {
        PluginRelease::query()->create([
            'slug' => 'ebq-seo',
            'version' => '9.9.9',
            'channel' => 'stable',
            'status' => PluginRelease::STATUS_PUBLISHED,
            'zip_path' => PluginRelease::ZIP_PUBLIC_BUILD,
            'published_at' => now(),
        ]);
        PluginRelease::query()->create([
            'slug' => 'ebq-seo',
            'version' => '9.9.10-beta',
            'channel' => 'beta',
            'status' => PluginRelease::STATUS_PUBLISHED,
            'zip_path' => PluginRelease::ZIP_PUBLIC_BUILD,
            'published_at' => now(),
        ]);

        $this->getJson(route('wordpress.plugin.version', ['channel' => 'stable']))
            ->assertOk()
            ->assertJsonPath('version', '9.9.9')
            ->assertJsonPath('channel', 'stable');

        $this->getJson(route('wordpress.plugin.version', ['channel' => 'beta']))
            ->assertOk()
            ->assertJsonPath('version', '9.9.10-beta')
            ->assertJsonPath('channel', 'beta');
    }

    public function test_admin_can_create_scheduled_plugin_release(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.plugin-releases.store'), [
            'version' => '2.3.0',
            'channel' => 'stable',
            'publish_mode' => 'schedule',
            'publish_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'release_notes' => 'Scheduled release',
        ])->assertRedirect();

        $this->assertDatabaseHas('plugin_releases', [
            'version' => '2.3.0',
            'channel' => 'stable',
            'status' => PluginRelease::STATUS_SCHEDULED,
            'zip_path' => PluginRelease::ZIP_PUBLIC_BUILD,
        ]);
    }
}
