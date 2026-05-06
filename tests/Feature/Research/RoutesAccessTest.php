<?php

namespace Tests\Feature\Research;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutesAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private static function routes(): array
    {
        return [
            'research.index',
            'research.keywords',
            'research.topics',
            'research.serp',
            'research.competitors',
            'research.gap',
            'research.briefs',
            'research.authority',
            'research.coverage',
            'research.internal-links',
            'research.opportunities',
            'research.alerts',
            'research.reverse',
        ];
    }

    private function ownedSession(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $website = Website::factory()->create(['user_id' => $user->id, 'domain' => 'site.test']);
        $this->actingAs($user);
        session(['current_website_id' => $website->id]);

        return $user;
    }

    public function test_logged_out_routes_redirect_to_login(): void
    {
        foreach (self::routes() as $name) {
            $resp = $this->get(route($name));
            $this->assertTrue(
                in_array($resp->status(), [302], true),
                "Route {$name} should redirect when unauthenticated, got {$resp->status()}."
            );
        }
    }

    public function test_owner_can_access_every_research_route(): void
    {
        $this->ownedSession();

        foreach (self::routes() as $name) {
            $resp = $this->get(route($name));
            $this->assertSame(200, $resp->status(), "Route {$name} should return 200 for an owner with feature access — got {$resp->status()}.");
        }
    }

    public function test_brief_show_route_requires_ownership(): void
    {
        $owner = $this->ownedSession();
        $brief = \App\Models\Research\ContentBrief::create([
            'website_id' => Website::query()->where('user_id', $owner->id)->value('id'),
            'keyword_id' => \App\Models\Research\Keyword::firstOrCreate(
                ['query_hash' => \App\Models\Research\Keyword::hashFor('matcha latte'), 'country' => 'us', 'language' => 'en'],
                ['query' => 'matcha latte', 'normalized_query' => 'matcha latte']
            )->id,
            'created_by' => $owner->id,
            'payload' => ['stub' => true],
        ]);

        $this->get(route('research.briefs.show', $brief->id))->assertOk();

        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $strangerWebsite = Website::factory()->create(['user_id' => $stranger->id]);
        $this->actingAs($stranger);
        session(['current_website_id' => $strangerWebsite->id]);

        $this->get(route('research.briefs.show', $brief->id))->assertForbidden();
    }
}
