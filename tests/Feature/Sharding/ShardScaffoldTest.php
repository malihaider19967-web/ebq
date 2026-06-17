<?php

namespace Tests\Feature\Sharding;

use App\Models\DbNode;
use App\Support\ShardContext;
use App\Support\ShardManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShardScaffoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_node_has_ulid_key_and_scopes(): void
    {
        $primary = DbNode::create([
            'name' => 'primary', 'role' => DbNode::ROLE_PRIMARY,
            'status' => DbNode::STATUS_ACTIVE, 'is_pinned' => true,
            'private_ip' => '10.0.0.2', 'db_name' => 'ebq',
        ]);
        $draining = DbNode::create([
            'name' => 'shard-old', 'role' => DbNode::ROLE_TENANT,
            'status' => DbNode::STATUS_DRAINING, 'private_ip' => '10.0.0.5', 'db_name' => 'ebq_t1',
        ]);
        DbNode::create([
            'name' => 'shard-failed', 'role' => DbNode::ROLE_TENANT,
            'status' => DbNode::STATUS_FAILED,
        ]);

        // ULID primary key (26-char string), not an auto-increment integer.
        $this->assertIsString($primary->id);
        $this->assertSame(26, strlen($primary->id));
        $this->assertSame('node:'.$primary->id, $primary->connectionName());

        $this->assertSame(1, DbNode::active()->count());            // only the active one
        $this->assertSame(2, DbNode::billable()->count());          // active + draining
        $this->assertSame(0, DbNode::query()->drainable()->count()); // active one is pinned
    }

    public function test_shard_manager_registers_connection_for_ready_nodes_only(): void
    {
        DbNode::create([
            'name' => 'ready', 'role' => DbNode::ROLE_TENANT, 'status' => DbNode::STATUS_ACTIVE,
            'private_ip' => '10.0.0.7', 'db_name' => 'ebq_t7',
        ]);
        // Not ready: active but no ip/db assigned yet → must be skipped.
        DbNode::create([
            'name' => 'pending-ip', 'role' => DbNode::ROLE_TENANT, 'status' => DbNode::STATUS_ACTIVE,
        ]);

        ShardManager::flush(); // boot cached an empty list before migration
        app(ShardManager::class)->register();

        $ready = DbNode::where('name', 'ready')->first();
        $conn = config('database.connections.'.$ready->connectionName());

        $this->assertNotNull($conn, 'ready node should be registered as a connection');
        $this->assertSame('10.0.0.7', $conn['host']);
        $this->assertSame('ebq_t7', $conn['database']);
        // Clones the central template's driver/charset — only host+db differ.
        $this->assertSame(config('database.connections.global.driver'), $conn['driver']);

        $pending = DbNode::where('name', 'pending-ip')->first();
        $this->assertNull(config('database.connections.'.$pending->connectionName()));
    }

    public function test_shard_lock_marks_tenant_and_crawl_site_migrating(): void
    {
        $lock = \App\Support\ShardLock::class;

        $this->assertFalse($lock::websiteLocked('w1'));
        $this->assertFalse($lock::websiteLocked(null));
        $this->assertFalse($lock::websiteLocked(''));

        $lock::lockWebsite('w1');
        $this->assertTrue($lock::websiteLocked('w1'));
        $this->assertFalse($lock::websiteLocked('w2'));
        $lock::unlockWebsite('w1');
        $this->assertFalse($lock::websiteLocked('w1'));

        $lock::lockCrawlSite('cs1');
        $this->assertTrue($lock::crawlSiteLocked('cs1'));
        $lock::unlockCrawlSite('cs1');
        $this->assertFalse($lock::crawlSiteLocked('cs1'));
    }

    public function test_shard_context_defaults_to_eloquent_default_connection(): void
    {
        $ctx = app(ShardContext::class);

        $this->assertNull($ctx->tenantConnection());
        $this->assertNull($ctx->crawlConnection());

        $ctx->setTenantConnection('node:abc');
        $ctx->setCrawlConnection('node:xyz');
        $this->assertSame('node:abc', $ctx->tenantConnection());
        $this->assertSame('node:xyz', $ctx->crawlConnection());

        $ctx->reset();
        $this->assertNull($ctx->tenantConnection());
        $this->assertNull($ctx->crawlConnection());
    }
}
