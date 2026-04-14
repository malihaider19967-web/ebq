<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BacklinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_backlinks(): void
    {
        $this->get(route('backlinks.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_website_is_redirected_to_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('backlinks.index'))->assertRedirect(route('onboarding'));
    }

    public function test_onboarded_user_can_view_backlinks_page(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->withSession(['current_website_id' => $website->id])
            ->get(route('backlinks.index'))
            ->assertOk()
            ->assertSee('Add backlink')
            ->assertSee('Bulk edit by date');
    }
}
