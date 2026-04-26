<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-website subscription tier. Drives gating for AI features — title /
 * meta rewrites, content briefs, redirect matching — that consume LLM
 * tokens. Free tier sees the surface but with a "Pro" CTA in place of the
 * action button; Pro tier sees the actual tools.
 *
 * Stored on Website (not User) because billing in this product attaches
 * to the audited site, not the human account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->string('tier', 16)->default('free')->after('domain');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
