<?php

namespace Tests\Feature\Research;

use App\Models\ClientActivity;
use App\Models\Plan;
use App\Models\User;
use App\Models\Website;
use App\Services\Research\Quota\ResearchQuotaService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlanQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlan(string $slug, ?array $researchLimits = null): User
    {
        (new PlanSeeder())->run();
        $plan = Plan::query()->where('slug', $slug)->firstOrFail();
        if ($researchLimits !== null) {
            $plan->forceFill(['research_limits' => $researchLimits])->save();
        }

        return User::factory()->create(['current_plan_slug' => $slug]);
    }

    public function test_plan_research_limit_overrides_config_default(): void
    {
        config()->set('services.research.limits', [
            'keyword_lookup' => 10,
            'serp_fetch' => 5,
            'llm_call' => 200,
            'brief' => 3,
        ]);

        $user = $this->userWithPlan('starter', [
            'keyword_lookup' => 7777,
            'serp_fetch' => 9999,
        ]);
        $website = Website::factory()->create(['user_id' => $user->id]);

        $service = new ResearchQuotaService();

        $this->assertSame(7777, $service->limit($website, 'keyword_lookup'));
        $this->assertSame(9999, $service->limit($website, 'serp_fetch'));
        $this->assertSame(200, $service->limit($website, 'llm_call'), 'Falls back to config default when missing on Plan.');
        $this->assertSame(3, $service->limit($website, 'brief'));
    }

    public function test_assert_can_spend_throws_when_quota_exhausted(): void
    {
        $user = $this->userWithPlan('free', ['keyword_lookup' => 5]);
        $website = Website::factory()->create(['user_id' => $user->id]);

        ClientActivity::create([
            'user_id' => $user->id,
            'website_id' => $website->id,
            'type' => 'research.keyword_lookup',
            'provider' => 'keywords_everywhere',
            'units_consumed' => 5,
        ]);

        $service = new ResearchQuotaService();

        $this->assertSame(0, $service->remaining($website, 'keyword_lookup'));

        try {
            $service->assertCanSpend($website, 'keyword_lookup', 1);
            $this->fail('Expected HttpException to be thrown.');
        } catch (HttpException $e) {
            $this->assertSame(402, $e->getStatusCode());
        }
    }

    public function test_used_only_counts_current_month(): void
    {
        $user = $this->userWithPlan('free');
        $website = Website::factory()->create(['user_id' => $user->id]);

        $old = ClientActivity::create([
            'user_id' => $user->id,
            'website_id' => $website->id,
            'type' => 'research.keyword_lookup',
            'provider' => 'keywords_everywhere',
            'units_consumed' => 50,
        ]);
        $old->forceFill([
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subMonths(2),
        ])->save();
        ClientActivity::create([
            'user_id' => $user->id,
            'website_id' => $website->id,
            'type' => 'research.keyword_lookup',
            'provider' => 'keywords_everywhere',
            'units_consumed' => 7,
        ]);

        $service = new ResearchQuotaService();

        $this->assertSame(7, $service->used($website->id, 'keyword_lookup'));
    }
}
