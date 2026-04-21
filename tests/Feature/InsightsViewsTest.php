<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_insight_cards(): void
    {
        $user = User::factory()->create();
        Website::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cannibalizations')
            ->assertSee('Striking distance');
    }

    public function test_reports_page_renders_insights_panel(): void
    {
        $user = User::factory()->create();
        Website::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Insights')
            ->assertSee('Keyword cannibalization');
    }
}
