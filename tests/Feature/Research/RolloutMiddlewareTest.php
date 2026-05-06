<?php

namespace Tests\Feature\Research;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolloutMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function ownedSession(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'site.test']);
        $this->actingAs($user);
        session(['current_website_id' => $website->id]);

        return [$user, $website];
    }

    public function test_ga_mode_admits_every_owner(): void
    {
        config()->set('research.rollout.mode', 'ga');
        config()->set('research.rollout.allowlist', []);
        [, $website] = $this->ownedSession();

        $this->get(route('research.index'))->assertOk();
    }

    public function test_admin_mode_blocks_websites_not_in_allowlist(): void
    {
        config()->set('research.rollout.mode', 'admin');
        config()->set('research.rollout.allowlist', [99999999]);
        [, $website] = $this->ownedSession();

        $this->assertNotContains($website->id, [99999999]);
        $resp = $this->get(route('research.index'));
        $this->assertSame(302, $resp->status());
        $this->assertSame(route('dashboard'), $resp->headers->get('Location'));
    }

    public function test_admin_mode_admits_websites_in_allowlist(): void
    {
        [, $website] = $this->ownedSession();
        config()->set('research.rollout.mode', 'admin');
        config()->set('research.rollout.allowlist', [$website->id]);

        $this->get(route('research.index'))->assertOk();
    }

    public function test_cohort_mode_uses_same_allowlist_logic(): void
    {
        [, $website] = $this->ownedSession();
        config()->set('research.rollout.mode', 'cohort');
        config()->set('research.rollout.allowlist', [$website->id, $website->id + 9999]);

        $this->get(route('research.index'))->assertOk();
    }
}
