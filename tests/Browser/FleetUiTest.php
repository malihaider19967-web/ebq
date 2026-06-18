<?php

namespace Tests\Browser;

use App\Models\CrawlSite;
use App\Models\DbNode;
use App\Models\User;
use App\Models\Website;
use App\Models\WorkerNode;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Full-lifecycle UI test of BOTH fleets, driven entirely through the admin panel
 * (no CLI). Three ordered phases:
 *   1) reset to a clean slate (move tenant + crawl-site back, destroy stray nodes)
 *   2) fresh run on new nodes — provision DB shard + crawl worker, move the tenant
 *      AND the crawl-site onto the DB node, verify as the client (screenshots →
 *      the /admin/fleet-test slideshow)
 *   3) teardown — move both back, destroy both nodes, confirm clean
 * Cap honoured: 1 DB node + 1 crawl worker (the DB node hosts both the moved
 * tenant tier and the moved crawl tier).
 */
class FleetUiTest extends DuskTestCase
{
    private const ADMIN = 'malihaider19967@gmail.com';
    private const CLIENT = 'malihaider1996@gmail.com';
    private const SITE = 'pubgnamegenerator.net';
    private const PRIMARY = '01kvbbjhe7gba8qger0aeyec9q';
    private const CRAWL_SITE = '01kvbh3c7ksr66s267pzqxx16q';

    private function admin(): User
    {
        return User::where('email', self::ADMIN)->firstOrFail();
    }

    private function clientId(): string
    {
        return (string) User::where('email', self::CLIENT)->value('id');
    }

    private function siteNodeId(): ?string
    {
        return Website::where('domain', self::SITE)->value('db_node_id');
    }

    private function crawlNodeId(): ?string
    {
        return CrawlSite::where('id', self::CRAWL_SITE)->value('crawl_node_id');
    }

    private function pollUntil(callable $check, int $timeout = 300, int $interval = 6)
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

    /** Visit an admin page, stop its auto-refresh + auto-accept confirm dialogs. */
    private function open(Browser $b, string $url): void
    {
        $b->visit($url)->pause(1500);
        $b->script('for (var i = 1; i < 100000; i++) { window.clearInterval(i); } window.confirm = function () { return true; };');
        $b->pause(400);
    }

    // ── Phase 1: reset to a clean slate (no screenshots) ──────────────────────
    public function test_1_reset_to_clean_slate(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin()->id);

            if (($n = $this->siteNodeId()) !== null && $n !== self::PRIMARY) {
                $this->open($browser, '/admin/db-fleet');
                $browser->select('kind', 'tenant')->type('id', $this->clientId())->select('to', self::PRIMARY)->press('Move');
                $this->pollUntil(fn () => $this->siteNodeId() === self::PRIMARY, 180);
            }
            if (($n = $this->crawlNodeId()) !== null && $n !== self::PRIMARY) {
                $this->open($browser, '/admin/db-fleet');
                $browser->select('kind', 'crawl')->type('id', self::CRAWL_SITE)->select('to', self::PRIMARY)->press('Move');
                $this->pollUntil(fn () => $this->crawlNodeId() === self::PRIMARY, 180);
            }
            if (DbNode::where('is_pinned', false)->exists()) {
                $this->open($browser, '/admin/db-fleet');
                $browser->press('destroy');
                $this->pollUntil(fn () => ! DbNode::where('is_pinned', false)->exists(), 90);
            }
            if (WorkerNode::where('is_pinned', false)->exists()) {
                $this->open($browser, '/admin/fleet');
                $browser->press('Destroy');
                $this->pollUntil(fn () => ! WorkerNode::where('is_pinned', false)->exists(), 90);
            }

            $this->assertFalse(DbNode::where('is_pinned', false)->exists());
            $this->assertFalse(WorkerNode::where('is_pinned', false)->exists());
        });
    }

    // ── Phase 2: fresh run on new nodes (records the slideshow) ───────────────
    public function test_2_provision_move_verify(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin()->id);

            // 1) Provision DB shard node
            $this->open($browser, '/admin/db-fleet');
            $browser->assertSee('Database fleet')->screenshot('fleet-01-dbfleet-initial');
            $browser->press('+ Provision tenant node')->pause(2500)->screenshot('fleet-02-db-provision-clicked');
            $dbNode = $this->pollUntil(fn () => DbNode::where('is_pinned', false)->where('status', DbNode::STATUS_ACTIVE)->first(), 420);
            $this->assertNotNull($dbNode, 'DB node did not reach active via UI');
            $this->open($browser, '/admin/db-fleet');
            $browser->screenshot('fleet-03-db-node-active');

            // 2) Provision crawl worker
            $this->open($browser, '/admin/fleet');
            $browser->screenshot('fleet-04-crawlfleet-initial');
            $browser->press('+ Provision a box')->pause(2500)->screenshot('fleet-05-crawl-provision-clicked');
            $worker = $this->pollUntil(fn () => WorkerNode::where('is_pinned', false)->where('status', WorkerNode::STATUS_ACTIVE)->first(), 420);
            $this->assertNotNull($worker, 'crawl worker did not reach active via UI');
            $this->open($browser, '/admin/fleet');
            $browser->screenshot('fleet-06-crawl-node-active');

            // 3) Move TENANT onto the DB node
            $this->open($browser, '/admin/db-fleet');
            $browser->screenshot('fleet-07-before-moves');
            $browser->select('kind', 'tenant')->type('id', $this->clientId())->select('to', $dbNode->id)->press('Move')
                ->pause(2500)->screenshot('fleet-08-tenant-move-submitted');
            $this->pollUntil(fn () => $this->siteNodeId() === $dbNode->id, 180);
            $this->assertSame($dbNode->id, $this->siteNodeId());
            $this->open($browser, '/admin/db-fleet');
            $browser->screenshot('fleet-09-tenant-moved');

            // 4) Move CRAWL-SITE onto the same DB node (crawl tier shards by domain)
            $browser->select('kind', 'crawl')->type('id', self::CRAWL_SITE)->select('to', $dbNode->id)->press('Move')
                ->pause(2500)->screenshot('fleet-10-crawlsite-move-submitted');
            $this->pollUntil(fn () => $this->crawlNodeId() === $dbNode->id, 180);
            $this->assertSame($dbNode->id, $this->crawlNodeId());
            $this->open($browser, '/admin/db-fleet');
            $browser->screenshot('fleet-11-crawlsite-moved');

            // 5) Client verify (tenant tier + crawl tier both read from the new node)
            $browser->loginAs(User::where('email', self::CLIENT)->value('id'))
                ->visit('/dashboard')->assertPathIs('/dashboard')->pause(6000)
                ->screenshot('fleet-12-client-dashboard')->assertSee(self::SITE)->assertDontSee('SQLSTATE')->assertDontSee('Whoops');
            $browser->visit('/keywords')->pause(5000)
                ->screenshot('fleet-13-client-keywords')->assertDontSee('SQLSTATE')->assertDontSee('Whoops');
        });
    }

    // ── Phase 3: teardown (records the final slides) ──────────────────────────
    public function test_3_teardown(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin()->id);

            // Move tenant back
            $this->open($browser, '/admin/db-fleet');
            $browser->select('kind', 'tenant')->type('id', $this->clientId())->select('to', self::PRIMARY)->press('Move')
                ->pause(2500)->screenshot('fleet-14-tenant-moved-back');
            $this->pollUntil(fn () => $this->siteNodeId() === self::PRIMARY, 180);

            // Move crawl-site back
            $this->open($browser, '/admin/db-fleet');
            $browser->select('kind', 'crawl')->type('id', self::CRAWL_SITE)->select('to', self::PRIMARY)->press('Move')
                ->pause(2500)->screenshot('fleet-15-crawlsite-moved-back');
            $this->pollUntil(fn () => $this->crawlNodeId() === self::PRIMARY, 180);

            // Destroy the (now empty) DB shard node
            $this->open($browser, '/admin/db-fleet');
            if (DbNode::where('is_pinned', false)->exists()) {
                $browser->press('destroy')->pause(2500);
                $this->pollUntil(fn () => ! DbNode::where('is_pinned', false)->exists(), 90);
            }
            $this->open($browser, '/admin/db-fleet');
            $browser->screenshot('fleet-16-db-node-destroyed');

            // Destroy the crawl worker
            $this->open($browser, '/admin/fleet');
            if (WorkerNode::where('is_pinned', false)->exists()) {
                $browser->press('Destroy')->pause(2500);
                $this->pollUntil(fn () => ! WorkerNode::where('is_pinned', false)->exists(), 90);
            }
            $this->open($browser, '/admin/fleet');
            $browser->screenshot('fleet-17-crawl-node-destroyed');

            $this->open($browser, '/admin/db-fleet');
            $browser->screenshot('fleet-18-clean-final');

            $this->assertFalse(DbNode::where('is_pinned', false)->exists());
            $this->assertFalse(WorkerNode::where('is_pinned', false)->exists());
        });
    }
}
