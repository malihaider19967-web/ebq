<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sitewide SEO Analyzer runs (Phase 11). One row per audit, with the
 * full check array stored as JSON so adding new checks doesn't
 * require a schema migration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_audit_runs', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('website_id');
            $t->string('status', 16)->default('completed'); // queued | running | completed | failed
            $t->json('checks');
            $t->timestamps();

            $t->index('website_id');
            $t->index(['website_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_audit_runs');
    }
};
