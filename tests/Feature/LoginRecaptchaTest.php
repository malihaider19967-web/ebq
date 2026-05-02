<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginRecaptchaTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_without_recaptcha_when_keys_unset(): void
    {
        Config::set('services.recaptcha.site_key', '');
        Config::set('services.recaptcha.secret_key', '');

        $user = User::factory()->create(['email' => 'plain-login@example.com']);

        $this->post(route('login'), [
            'email' => 'plain-login@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_requires_response_when_recaptcha_configured(): void
    {
        Config::set('services.recaptcha.site_key', 'test-site-key');
        Config::set('services.recaptcha.secret_key', 'test-secret-key');

        User::factory()->create(['email' => 'exists@example.com']);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'exists@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();
    }

    public function test_login_succeeds_when_recaptcha_verifies(): void
    {
        Config::set('services.recaptcha.site_key', 'test-site-key');
        Config::set('services.recaptcha.secret_key', 'test-secret-key');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'challenge_ts' => now()->toIso8601String(),
                'hostname' => 'localhost',
            ], 200),
        ]);

        $user = User::factory()->create(['email' => 'captcha-login@example.com']);

        $this->post(route('login'), [
            'email' => 'captcha-login@example.com',
            'password' => 'password',
            'g-recaptcha-response' => 'test-token',
        ]);

        $this->assertAuthenticatedAs($user);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
                && $request['secret'] === 'test-secret-key'
                && $request['response'] === 'test-token';
        });
    }
}
