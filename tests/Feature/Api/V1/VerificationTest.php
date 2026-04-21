<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_challenge_issues_a_code_for_the_owner(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/verify/challenge', ['website_id' => $website->id]);

        $response->assertOk()
            ->assertJsonStructure(['challenge_code', 'expires_at', 'verify_path']);

        $this->assertDatabaseHas('website_verifications', [
            'website_id' => $website->id,
            'challenge_code' => $response->json('challenge_code'),
        ]);
    }

    public function test_challenge_is_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->postJson('/api/v1/verify/challenge', ['website_id' => $website->id])
            ->assertForbidden();
    }

    public function test_confirm_verifies_and_mints_token(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $verification = WebsiteVerification::issueFor($website);

        Http::fake([
            'https://'.$website->domain.'/.well-known/ebq-verification.txt' => Http::response($verification->challenge_code, 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/verify/confirm', ['website_id' => $website->id]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'website_id', 'abilities', 'issued_at']);

        $this->assertNotNull(WebsiteVerification::query()
            ->where('website_id', $website->id)
            ->whereNotNull('verified_at')
            ->first()
        );
        $this->assertSame(1, PersonalAccessToken::query()
            ->where('tokenable_type', Website::class)
            ->where('tokenable_id', $website->id)
            ->count()
        );
    }

    public function test_confirm_rejects_when_well_known_body_mismatches(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        WebsiteVerification::issueFor($website);

        Http::fake([
            'https://'.$website->domain.'/.well-known/ebq-verification.txt' => Http::response('wrong-value', 200),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/verify/confirm', ['website_id' => $website->id])
            ->assertStatus(422)
            ->assertJson(['error' => 'mismatch']);

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_confirm_rejects_when_well_known_fetch_fails(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        WebsiteVerification::issueFor($website);

        Http::fake([
            'https://'.$website->domain.'/.well-known/ebq-verification.txt' => Http::response('', 404),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/verify/confirm', ['website_id' => $website->id])
            ->assertStatus(422)
            ->assertJson(['error' => 'non_2xx']);
    }
}
