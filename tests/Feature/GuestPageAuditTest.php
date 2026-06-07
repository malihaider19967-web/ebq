<?php

namespace Tests\Feature;

use App\Jobs\RunGuestPageAudit;
use App\Models\GuestPageAudit;
use App\Services\PageAuditService;
use App\Support\Audit\SafeHttpGuard;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class GuestPageAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // CSRF is not auto-bypassed in this app's test env; the browser form
        // ships a token in production. Skip the check for these API-shaped posts.
        $this->withoutMiddleware(ValidateCsrfToken::class);
        // Cached config bakes in real reCAPTCHA keys; disable it so these tests
        // exercise the URL/keyword path deterministically.
        config(['services.recaptcha.site_key' => '', 'services.recaptcha.secret_key' => '']);
    }

    protected function tearDown(): void
    {
        Http::fake();
        Mockery::close();
        parent::tearDown();
    }

    private function fakePage(): void
    {
        $html = '<!DOCTYPE html><html lang="en"><head>'
            .'<title>Best SEO Tools for teams</title>'
            .'<meta name="description" content="A guide to the best seo tools.">'
            .'</head><body><h1>Best SEO Tools</h1><p>'
            .str_repeat('The best seo tools help teams ship faster. ', 40)
            .'</p></body></html>';

        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);
    }

    public function test_audit_guest_runs_without_website_and_uses_manual_keyword(): void
    {
        $guard = Mockery::mock(SafeHttpGuard::class);
        $guard->shouldReceive('check')->andReturn(['ok' => true]);
        $this->app->instance(SafeHttpGuard::class, $guard);

        // Isolate the on-page + keyword engine: no Serper key → benchmark skipped.
        config(['services.serper.key' => '']);
        $this->fakePage();

        $outcome = $this->app->make(PageAuditService::class)
            ->auditGuest('https://example.com/page', 'best seo tools');

        $this->assertSame('completed', $outcome['status']);
        $this->assertIsArray($outcome['result']);

        // Keyword analysis works purely off the manual keyword (no GSC).
        $this->assertTrue($outcome['result']['keywords']['available']);
        $this->assertSame('custom_audit', $outcome['result']['keywords']['primary_source']);
        $this->assertSame('best seo tools', $outcome['primary_keyword']);

        // CWV (Lighthouse) is never run for guests; benchmark is skipped here
        // only because Serper isn't configured in this test.
        $this->assertArrayNotHasKey('core_web_vitals', $outcome['result']);
        $this->assertArrayNotHasKey('benchmark', $outcome['result']);

        // On-page analysis is present.
        $this->assertArrayHasKey('metadata', $outcome['result']);
        $this->assertArrayHasKey('recommendations', $outcome['result']);
    }

    public function test_audit_guest_includes_competitors_when_serper_configured(): void
    {
        $guard = Mockery::mock(SafeHttpGuard::class);
        $guard->shouldReceive('check')->andReturn(['ok' => true]);
        $this->app->instance(SafeHttpGuard::class, $guard);

        config(['services.serper.key' => 'test-serper-key']);

        $page = '<!DOCTYPE html><html lang="en"><head><title>Best SEO Tools</title>'
            .'<meta name="description" content="best seo tools guide"></head><body><h1>Best SEO Tools</h1><p>'
            .str_repeat('The best seo tools help teams ship faster. ', 60).'</p></body></html>';

        Http::fake(function ($request) use ($page) {
            $url = $request->url();
            if (str_contains($url, 'serper.dev/search')) {
                return Http::response(['organic' => [
                    ['link' => 'https://alpha-comp.test/p1', 'title' => 'Alpha', 'position' => 1],
                    ['link' => 'https://beta-comp.test/p2', 'title' => 'Beta', 'position' => 2],
                    ['link' => 'https://gamma-comp.test/p3', 'title' => 'Gamma', 'position' => 3],
                ]], 200);
            }

            return Http::response($page, 200, ['Content-Type' => 'text/html']);
        });

        $outcome = $this->app->make(PageAuditService::class)
            ->auditGuest('https://example.com/page', 'best seo tools');

        $this->assertSame('completed', $outcome['status']);
        $this->assertArrayHasKey('benchmark', $outcome['result']);
        $this->assertNotEmpty($outcome['result']['benchmark']['competitors']);
        $this->assertSame('best seo tools', $outcome['result']['benchmark']['keyword']);
        // CWV still never runs for guests.
        $this->assertArrayNotHasKey('core_web_vitals', $outcome['result']);
    }

    public function test_audit_guest_honors_chosen_serp_country(): void
    {
        $guard = Mockery::mock(SafeHttpGuard::class);
        $guard->shouldReceive('check')->andReturn(['ok' => true]);
        $this->app->instance(SafeHttpGuard::class, $guard);

        config(['services.serper.key' => 'test-serper-key']);
        $this->fakePage();
        Http::fake(['*serper.dev*' => Http::response(['organic' => []], 200), '*' => Http::response('<html lang="en"><head><title>t</title></head><body><p>x</p></body></html>', 200)]);

        $outcome = $this->app->make(PageAuditService::class)
            ->auditGuest('https://example.com/page', 'best seo tools', 'gb');

        $this->assertSame('completed', $outcome['status']);
        // The chosen gl reached the benchmark/locale resolution.
        $this->assertSame('gb', $outcome['result']['page_locale']['serp_gl_user_chosen'] ?? null);
    }

    public function test_store_endpoint_persists_chosen_country(): void
    {
        Queue::fake();

        $this->postJson(route('guest-audit.store'), [
            'url' => 'example.com/page',
            'keyword' => 'best seo tools',
            'country' => 'gb',
        ])->assertStatus(202);

        $this->assertDatabaseHas('guest_page_audits', ['url' => 'https://example.com/page', 'serp_gl' => 'gb']);
    }

    public function test_store_endpoint_rejects_invalid_country(): void
    {
        Queue::fake();

        $this->postJson(route('guest-audit.store'), [
            'url' => 'example.com/page',
            'keyword' => 'best seo tools',
            'country' => 'zz',
        ])->assertStatus(422)->assertJsonValidationErrors(['country']);

        Queue::assertNothingPushed();
    }

    /** Carry response Set-Cookie values into the next request (they're encrypted; the middleware round-trips them). */
    private function cookiesFrom($response): array
    {
        $out = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $out[$cookie->getName()] = $cookie->getValue();
        }

        return $out;
    }

    public function test_progressive_gate_first_free_second_email_third_signup(): void
    {
        Queue::fake();
        // Plaintext counter cookie end-to-end so the round-trip is deterministic:
        // disable the harness's outgoing encryption AND the decrypting middleware,
        // and send credentials so JSON requests actually include cookies.
        $this->disableCookieEncryption();
        $this->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class);
        $this->withCredentials();
        $guard = Mockery::mock(SafeHttpGuard::class);
        $guard->shouldReceive('check')->andReturn(['ok' => true]);
        $this->app->instance(SafeHttpGuard::class, $guard);

        // 1st audit — free, no email, queued. Sets the counter cookie.
        $r1 = $this->postJson(route('guest-audit.store'), ['url' => 'a.com/p', 'keyword' => 'best seo tools']);
        $r1->assertStatus(202)->assertJsonMissing(['emailed' => true]);
        $cookies = $this->cookiesFrom($r1);
        Queue::assertPushed(RunGuestPageAudit::class, 1);

        // 2nd audit without email — gated, asks for email, no audit queued.
        $r2 = $this->withCookies($cookies)->postJson(route('guest-audit.store'), ['url' => 'a.com/p2', 'keyword' => 'best seo tools']);
        $r2->assertStatus(200)->assertJsonPath('require', 'email');
        Queue::assertPushed(RunGuestPageAudit::class, 1); // still only the first

        // 2nd audit with name + email — runs, emailed (no on-screen results), lead captured.
        $r2b = $this->withCookies($cookies)->postJson(route('guest-audit.store'), ['url' => 'a.com/p2', 'keyword' => 'best seo tools', 'name' => 'Jane Doe', 'email' => 'lead@example.com']);
        $r2b->assertStatus(202)
            ->assertJsonPath('emailed', true)
            ->assertJsonMissing(['results_url' => true]);
        $this->assertArrayNotHasKey('results_url', $r2b->json()); // 2nd audit must NOT expose on-screen results
        $this->assertDatabaseHas('guest_page_audits', ['url' => 'https://a.com/p2', 'email' => 'lead@example.com', 'name' => 'Jane Doe']);
        $this->assertDatabaseHas('leads', ['email' => 'lead@example.com', 'name' => 'Jane Doe', 'converted_at' => null]);
        $cookies2 = $this->cookiesFrom($r2b);

        // 3rd audit — blocked, signup gate.
        $r3 = $this->withCookies($cookies2)->postJson(route('guest-audit.store'), ['url' => 'a.com/p3', 'keyword' => 'best seo tools']);
        $r3->assertStatus(200)->assertJsonPath('require', 'signup')->assertJsonStructure(['register_url']);
        Queue::assertPushed(RunGuestPageAudit::class, 2); // first + second-with-email only
    }

    public function test_lead_is_tagged_converted_when_matching_user_signs_up(): void
    {
        $lead = \App\Models\Lead::capture('future@example.com', 'Future User');
        $this->assertNull($lead->converted_at);

        $user = \App\Models\User::factory()->create(['email' => 'future@example.com']);

        $lead->refresh();
        $this->assertNotNull($lead->converted_at);
        $this->assertSame($user->id, $lead->user_id);
    }

    public function test_lead_capture_tags_converted_if_user_already_exists(): void
    {
        $user = \App\Models\User::factory()->create(['email' => 'existing@example.com']);
        $lead = \App\Models\Lead::capture('existing@example.com', 'Existing');

        $this->assertNotNull($lead->converted_at);
        $this->assertSame($user->id, $lead->user_id);
    }

    public function test_admin_leads_page_lists_leads(): void
    {
        \App\Models\Lead::capture('shown@example.com', 'Shown Lead');
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.leads.index'))
            ->assertOk()
            ->assertSee('shown@example.com')
            ->assertSee('Shown Lead');
    }

    public function test_job_emails_link_when_guest_supplied_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $guard = Mockery::mock(SafeHttpGuard::class);
        $guard->shouldReceive('check')->andReturn(['ok' => true]);
        $this->app->instance(SafeHttpGuard::class, $guard);
        config(['services.serper.key' => '']); // no benchmark calls
        $this->fakePage();

        $audit = GuestPageAudit::start('https://ex.com/page', 'best seo tools', '127.0.0.1', null, 'lead@example.com');

        (new RunGuestPageAudit($audit->id))->handle($this->app->make(PageAuditService::class));

        $audit->refresh();
        $this->assertSame(GuestPageAudit::STATUS_COMPLETED, $audit->status);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\GuestAuditLinkMail::class, function ($mail) {
            return $mail->hasTo('lead@example.com');
        });
    }

    public function test_job_does_not_email_when_no_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $guard = Mockery::mock(SafeHttpGuard::class);
        $guard->shouldReceive('check')->andReturn(['ok' => true]);
        $this->app->instance(SafeHttpGuard::class, $guard);
        config(['services.serper.key' => '']);
        $this->fakePage();

        $audit = GuestPageAudit::start('https://ex.com/page', 'best seo tools', '127.0.0.1');
        (new RunGuestPageAudit($audit->id))->handle($this->app->make(PageAuditService::class));

        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_audit_guest_fails_gracefully_on_unsafe_url(): void
    {
        $guard = Mockery::mock(SafeHttpGuard::class);
        $guard->shouldReceive('check')->andReturn(['ok' => false, 'reason' => 'private_network_address']);
        $this->app->instance(SafeHttpGuard::class, $guard);

        $outcome = $this->app->make(PageAuditService::class)
            ->auditGuest('http://127.0.0.1/', 'whatever');

        $this->assertSame('failed', $outcome['status']);
        $this->assertNull($outcome['result']);
        $this->assertNotEmpty($outcome['error_message']);
    }

    public function test_store_endpoint_queues_an_audit_and_returns_token(): void
    {
        Queue::fake();

        $response = $this->postJson(route('guest-audit.store'), [
            'url' => 'example.com/page',
            'keyword' => 'best seo tools',
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['token', 'status_url', 'results_url']);

        $this->assertDatabaseHas('guest_page_audits', [
            'url' => 'https://example.com/page', // normalized
            'keyword' => 'best seo tools',
            'status' => GuestPageAudit::STATUS_QUEUED,
        ]);

        Queue::assertPushed(RunGuestPageAudit::class);
    }

    public function test_store_endpoint_validates_url_and_keyword(): void
    {
        Queue::fake();

        $this->postJson(route('guest-audit.store'), ['url' => '', 'keyword' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url', 'keyword']);

        Queue::assertNothingPushed();
    }

    public function test_store_endpoint_rate_limits_per_ip(): void
    {
        Queue::fake();
        RateLimiter::clear('guest-audit:m:127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('guest-audit.store'), [
                'url' => "example.com/page-{$i}",
                'keyword' => 'best seo tools',
            ])->assertStatus(202);
        }

        $this->postJson(route('guest-audit.store'), [
            'url' => 'example.com/page-blocked',
            'keyword' => 'best seo tools',
        ])->assertStatus(429);
    }
}
