<?php

namespace Tests\Feature;

use App\Services\PageAuditService;
use App\Support\Audit\SafeHttpGuard;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class PageAuditSerpCountryPeekTest extends TestCase
{
    protected function tearDown(): void
    {
        Http::fake();
        Mockery::close();
        parent::tearDown();
    }

    public function test_peek_requests_country_for_lang_en_without_region(): void
    {
        Http::fake([
            'https://example.test/page' => Http::response(
                '<!DOCTYPE html><html lang="en"><head><title>t</title></head><body><p>Hi</p></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $guard = Mockery::mock(SafeHttpGuard::class);
        $guard->shouldReceive('check')->andReturn(['ok' => true]);
        $this->app->instance(SafeHttpGuard::class, $guard);

        $peek = $this->app->make(PageAuditService::class)->peekSerpCountryChoiceNeeded(1, 'https://example.test/page');

        $this->assertTrue($peek['ok']);
        $this->assertTrue($peek['needs_serp_country_choice']);
    }

    public function test_peek_skips_choice_for_french_without_gl(): void
    {
        Http::fake([
            'https://example.test/fr' => Http::response(
                '<!DOCTYPE html><html lang="fr"><head><title>t</title></head><body><p>Bonjour</p></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $guard = Mockery::mock(SafeHttpGuard::class);
        $guard->shouldReceive('check')->andReturn(['ok' => true]);
        $this->app->instance(SafeHttpGuard::class, $guard);

        $peek = $this->app->make(PageAuditService::class)->peekSerpCountryChoiceNeeded(1, 'https://example.test/fr');

        $this->assertTrue($peek['ok']);
        $this->assertFalse($peek['needs_serp_country_choice']);
    }
}
