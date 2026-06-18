<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\WorkerNode;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Provision ONE fresh ephemeral crawl box entirely through the admin UI, and let it
 * boot to `active`. The point is to prove the bootstrap auto-deploys CURRENT code +
 * vendor (incl. Horizon) onto a box that came from an older snapshot — verified from
 * the backend after the run (sentinel file, Horizon container, .env stamps). The box
 * is left running for that backend check + the recrawl test; teardown is separate.
 */
class ProvisionCrawlBoxTest extends DuskTestCase
{
    private const ADMIN = 'malihaider19967@gmail.com';

    private function admin(): User
    {
        return User::where('email', self::ADMIN)->firstOrFail();
    }

    private function pollUntil(callable $check, int $timeout = 480, int $interval = 6)
    {
        $deadline = time() + $timeout;
        do {
            if ($v = $check()) {
                return $v;
            }
            sleep($interval);
        } while (time() < $deadline);

        return $check();
    }

    /** Visit, stop auto-refresh + auto-accept confirm dialogs. */
    private function open(Browser $b, string $url): void
    {
        $b->visit($url)->pause(1500);
        $b->script('for (var i = 1; i < 100000; i++) { window.clearInterval(i); } window.confirm = function () { return true; };');
        $b->pause(400);
    }

    public function test_provision_crawl_box_via_ui(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin()->id);

            $this->open($browser, '/admin/fleet');
            $browser->assertSee('Crawl workers')->screenshot('crawl-01-fleet-initial');

            $browser->press('+ Provision a box')->pause(3000)->screenshot('crawl-02-provision-clicked');

            $worker = $this->pollUntil(
                fn () => WorkerNode::where('is_pinned', false)->where('status', WorkerNode::STATUS_ACTIVE)->first(),
                480
            );
            $this->assertNotNull($worker, 'crawl box did not reach active via the UI');

            $this->open($browser, '/admin/fleet');
            $browser->screenshot('crawl-03-box-active');
        });
    }
}
