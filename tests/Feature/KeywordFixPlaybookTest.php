<?php

namespace Tests\Feature;

use App\Livewire\Keywords\KeywordFixPlaybook;
use App\Models\PageAuditReport;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class KeywordFixPlaybookTest extends TestCase
{
    use RefreshDatabase;

    private function seedFreshReport(Website $website, string $url): PageAuditReport
    {
        return PageAuditReport::create([
            'website_id' => $website->id,
            'page' => $url,
            'page_hash' => hash('sha256', $url),
            'status' => 'completed',
            'audited_at' => Carbon::now()->subMinutes(10),
            'result' => [
                'metadata' => ['title' => 'Old title', 'meta_description' => 'desc'],
                'content' => [
                    'body_excerpt' => 'Some copy about blue widgets and pricing.',
                    'word_count' => 700,
                    'headings' => [['level' => 1, 'text' => 'Blue widgets']],
                ],
                'recommendations' => [
                    ['id' => 'meta.title.kw', 'section' => 'Keywords', 'severity' => 'warning',
                        'title' => 'Add the keyword to the title', 'why' => 'Strongest signal.', 'fix' => 'Put "blue widgets" in the <title>.'],
                ],
            ],
        ]);
    }

    public function test_ready_playbook_renders_all_four_sections(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        $url = 'https://'.$website->domain.'/widgets';
        $this->seedFreshReport($website, $url);

        session(['current_website_id' => $website->id]);

        Livewire::actingAs($user)
            ->test(KeywordFixPlaybook::class, ['keyword' => 'blue widgets', 'pageUrl' => $url])
            ->assertSet('status', 'ready')
            ->assertSee('blue widgets')
            ->assertSee('On-page fixes')
            ->assertSee('Add the keyword to the title')
            ->assertSee('Title &amp; meta rewrites', false)
            ->assertSee('Content brief')
            ->assertSee('Internal links to add');
    }

    public function test_missing_keyword_fails_gracefully(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        session(['current_website_id' => $website->id]);

        Livewire::actingAs($user)
            ->test(KeywordFixPlaybook::class, ['keyword' => '', 'page' => ''])
            ->assertSet('status', 'failed')
            ->assertSee('Missing keyword or page');
    }

    public function test_user_without_website_access_is_blocked(): void
    {
        $owner = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $owner->id]);
        $intruder = User::factory()->create();

        session(['current_website_id' => $website->id]);

        Livewire::actingAs($intruder)
            ->test(KeywordFixPlaybook::class, ['keyword' => 'blue widgets', 'pageUrl' => 'https://x.test/p'])
            ->assertSet('status', 'failed');
    }
}
