<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WordPressEmbedReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_embed_reports_logs_in_and_redirects(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $url = URL::temporarySignedRoute('wordpress.embed.reports', now()->addMinutes(5), [
            'website' => $website->id,
            'view' => 'email',
        ]);

        $this->get($url)
            ->assertRedirect(route('reports.index', ['view' => 'email']));

        $this->assertAuthenticatedAs($user);
        $this->assertEquals($website->id, session('current_website_id'));
    }

    public function test_unsigned_embed_reports_is_forbidden(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $this->get('/wordpress/embed/reports?website='.$website->id.'&view=email')
            ->assertForbidden();
    }
}
