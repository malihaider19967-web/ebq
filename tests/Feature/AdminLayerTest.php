<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_open_admin_clients(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.clients.index'))
            ->assertForbidden();
    }

    public function test_admin_can_open_admin_clients(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Admin Clients');
    }

    public function test_admin_can_impersonate_client(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $client = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.clients.impersonate', $client))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($client);
        $this->assertDatabaseHas('client_activities', [
            'type' => 'admin.impersonation_started',
            'user_id' => $client->id,
            'actor_user_id' => $admin->id,
        ]);
    }
}
