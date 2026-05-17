<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthReportHqApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_hq_growth_report_preview_returns_report_payload(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $token = $website->createToken('test', ['read:insights'])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/hq/growth-report?report_type=weekly');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'domain',
                'report' => [
                    'period',
                    'analytics',
                    'search_console',
                    'backlinks',
                    'indexing',
                ],
            ]);
    }
}
