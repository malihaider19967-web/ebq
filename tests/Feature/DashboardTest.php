<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarded_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        Website::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
