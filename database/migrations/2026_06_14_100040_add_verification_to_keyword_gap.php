<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Verify with live rankings" for Keyword Gap Analysis: confirm the Missing
 * bucket against the real SERP. Per-row we store the competitor's actual
 * position (null = checked but not in top-10) and a verified_at stamp; the
 * analysis header carries batch progress so the Livewire poller can show it.
 * Additive only — safe `migrate --force` on production.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('keyword_gap_rows', function (Blueprint $table): void {
            $table->unsignedSmallInteger('competitor_position')->nullable()->after('our_position');
            $table->timestamp('verified_at')->nullable()->after('score_components');
        });

        Schema::table('keyword_gap_analyses', function (Blueprint $table): void {
            // null = never verified | verifying | completed | failed
            $table->string('verify_status', 20)->nullable()->after('reprocessed_at');
            $table->unsignedInteger('verify_total')->default(0)->after('verify_status');
            $table->unsignedInteger('verify_done')->default(0)->after('verify_total');
            $table->string('verify_error')->nullable()->after('verify_done');
            $table->timestamp('verified_at')->nullable()->after('verify_error');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_gap_rows', function (Blueprint $table): void {
            $table->dropColumn(['competitor_position', 'verified_at']);
        });
        Schema::table('keyword_gap_analyses', function (Blueprint $table): void {
            $table->dropColumn(['verify_status', 'verify_total', 'verify_done', 'verify_error', 'verified_at']);
        });
    }
};
