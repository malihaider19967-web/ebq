<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptive anti-blocking for the crawler:
 *  - websites.crawl_protection: per-site policy flag (null | 'cloudflare' | 'blocked')
 *    set when we detect Cloudflare/WAF or get blocked; drives proxy use for that site.
 *  - proxies: admin-managed proxy pool (the crawler also reads proxylist.txt at runtime).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->string('crawl_protection')->nullable();
            $table->timestamp('crawl_protection_at')->nullable();
        });

        Schema::create('proxies', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->nullable();
            // Normalised Guzzle proxy URL: scheme://[user:pass@]host:port
            $table->string('url', 512);
            $table->string('url_hash', 64)->unique();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('fail_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_ok_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxies');
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn(['crawl_protection', 'crawl_protection_at']);
        });
    }
};
