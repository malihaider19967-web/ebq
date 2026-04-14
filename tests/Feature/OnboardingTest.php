<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_route_requires_authentication(): void
    {
        $this->get(route('onboarding'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('onboarding'))->assertOk();
    }
}
