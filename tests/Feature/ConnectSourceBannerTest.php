<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ConnectSourceBannerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Render the app-shell banner partial in isolation (the full dashboard
     * route is gated by verified/feature middleware that isn't wired up in
     * this minimal test environment).
     */
    private function renderBanner(User $user, string $websiteId): string
    {
        $this->actingAs($user);
        session(['current_website_id' => $websiteId]);

        return View::make('partials.connect-source-banner')->render();
    }

    public function test_banner_prompts_to_connect_the_missing_source(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withGaOnly()->create(['user_id' => $user->id]);

        $html = $this->renderBanner($user, $website->id);

        $this->assertStringContainsString('Connect Search Console to unlock the full report', $html);
    }

    public function test_banner_absent_when_both_sources_connected(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->withBothSources()->create(['user_id' => $user->id]);

        $html = $this->renderBanner($user, $website->id);

        $this->assertStringNotContainsString('to unlock the full report', $html);
    }

    public function test_banner_absent_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $website = Website::factory()->withGaOnly()->create(['user_id' => $owner->id]);

        $html = $this->renderBanner($viewer, $website->id);

        $this->assertStringNotContainsString('to unlock the full report', $html);
    }
}
