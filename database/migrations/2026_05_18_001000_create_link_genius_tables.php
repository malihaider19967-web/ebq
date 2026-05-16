<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link Genius (Phase 3/4) persistence:
 *
 *   link_genius_links         — one row per discovered link from the
 *                               crawl. Powers the link-health overview,
 *                               broken-link list, and orphan calculation.
 *   link_genius_anchor_rules  — operator-defined bulk anchor replace
 *                               rules. Applied either manually from the
 *                               admin UI or automatically on `save_post`.
 *
 * Per-website scoped + indexed on the columns the admin filters /
 * sort against most: source post, target URL, status, kind.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('link_genius_links', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('website_id');
            $t->unsignedBigInteger('source_post_id')->nullable();
            $t->string('target_url', 500);
            $t->unsignedBigInteger('target_post_id')->nullable();
            $t->string('anchor', 500)->nullable();
            $t->string('kind', 16)->default('internal'); // internal | external
            $t->string('status', 16)->default('ok');     // ok | broken | redirect
            $t->integer('http_status')->nullable();
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamps();

            $t->index('website_id');
            $t->index(['website_id', 'kind', 'status']);
            $t->index(['website_id', 'source_post_id']);
            $t->index(['website_id', 'target_post_id']);
        });

        Schema::create('link_genius_anchor_rules', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('website_id');
            $t->string('anchor_pattern', 255);
            $t->string('replacement_anchor', 255)->nullable();
            $t->string('replacement_url', 500);
            $t->string('status', 16)->default('active'); // active | paused
            $t->unsignedInteger('applied_count')->default(0);
            $t->timestamp('last_applied_at')->nullable();
            $t->timestamps();

            $t->index('website_id');
            $t->index(['website_id', 'status']);
        });

        Schema::create('keyword_link_maps', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('website_id');
            $t->string('keyword', 255);
            $t->string('target_url', 500);
            $t->string('status', 16)->default('active');
            $t->timestamp('last_applied_at')->nullable();
            $t->timestamps();

            $t->index('website_id');
            $t->index(['website_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_link_maps');
        Schema::dropIfExists('link_genius_anchor_rules');
        Schema::dropIfExists('link_genius_links');
    }
};
