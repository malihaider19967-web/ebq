<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_page_audits', function (Blueprint $table) {
            if (! Schema::hasColumn('custom_page_audits', 'queued_at')) {
                $table->timestamp('queued_at')->nullable();
            }
            if (! Schema::hasColumn('custom_page_audits', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            if (! Schema::hasColumn('custom_page_audits', 'finished_at')) {
                $table->timestamp('finished_at')->nullable();
            }
            if (! Schema::hasColumn('custom_page_audits', 'attempts')) {
                $table->unsignedTinyInteger('attempts')->default(0);
            }
        });

        // Speed up the "is there an active job for this URL?" dedupe lookup.
        Schema::table('custom_page_audits', function (Blueprint $table) {
            $table->index(['website_id', 'status', 'page_url_hash'], 'cpa_website_status_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('custom_page_audits', function (Blueprint $table) {
            $table->dropIndex('cpa_website_status_hash_idx');
        });

        Schema::table('custom_page_audits', function (Blueprint $table) {
            $table->dropColumn(['queued_at', 'started_at', 'finished_at', 'attempts']);
        });
    }
};
