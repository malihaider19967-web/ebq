<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_bearer_is_rejected(): void
    {
        $this->getJson('/api/v1/dashboard')
            ->assertStatus(401)
            ->assertJson(['error' => 'missing_token']);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer nonsense')
            ->getJson('/api/v1/dashboard')
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_token']);
    }

    public function test_valid_token_resolves_to_correct_website(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $plain = $website->createToken('test', ['read:insights'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJson([
                'website_id' => $website->id,
                'domain' => $website->domain,
            ]);
    }

    public function test_token_without_required_ability_is_rejected(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $plain = $website->createToken('test', ['unrelated:ability'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/dashboard')
            ->assertStatus(403)
            ->assertJson(['error' => 'insufficient_ability']);
    }

    public function test_website_a_token_cannot_read_website_b_data(): void
    {
        $user = User::factory()->create();
        $aWebsite = Website::factory()->create(['user_id' => $user->id, 'domain' => 'a.example.com']);
        $bWebsite = Website::factory()->create(['user_id' => $user->id, 'domain' => 'b.example.com']);

        $plainA = $aWebsite->createToken('test', ['read:insights'])->plainTextToken;

        // Token A's /dashboard should return A's data, never B's.
        $response = $this->withHeader('Authorization', 'Bearer '.$plainA)
            ->getJson('/api/v1/dashboard')
            ->assertOk();

        $this->assertSame($aWebsite->id, $response->json('website_id'));
        $this->assertNotSame($bWebsite->id, $response->json('website_id'));
    }
}
