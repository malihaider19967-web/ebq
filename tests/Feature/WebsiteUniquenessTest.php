<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebsiteUniquenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_user_can_have_multiple_sourceless_websites(): void
    {
        $user = User::factory()->create();

        // Both store ga_property_id='' and gsc_site_url='' — this used to collide
        // on the old (user_id, ga_property_id, gsc_site_url) unique index.
        Website::create(['user_id' => $user->id, 'domain' => 'first.com', 'ga_property_id' => '', 'gsc_site_url' => '']);
        Website::create(['user_id' => $user->id, 'domain' => 'second.com', 'ga_property_id' => '', 'gsc_site_url' => '']);

        $this->assertSame(2, Website::where('user_id', $user->id)->count());
    }

    public function test_same_domain_for_same_user_is_rejected(): void
    {
        $user = User::factory()->create();
        Website::create(['user_id' => $user->id, 'domain' => 'dup.com', 'ga_property_id' => '', 'gsc_site_url' => '']);

        $this->expectException(UniqueConstraintViolationException::class);
        Website::create(['user_id' => $user->id, 'domain' => 'dup.com', 'ga_property_id' => '', 'gsc_site_url' => '']);
    }

    public function test_two_users_can_share_a_domain(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        Website::create(['user_id' => $a->id, 'domain' => 'shared.com', 'ga_property_id' => '', 'gsc_site_url' => '']);
        Website::create(['user_id' => $b->id, 'domain' => 'shared.com', 'ga_property_id' => '', 'gsc_site_url' => '']);

        $this->assertSame(2, Website::where('domain', 'shared.com')->count());
    }
}
