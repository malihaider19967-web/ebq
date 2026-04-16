<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('page_indexing_statuses')) {
            return;
        }

        Schema::table('page_indexing_statuses', function (Blueprint $table) {
            if (Schema::hasColumn('page_indexing_statuses', 'last_indexed_at')
                && ! Schema::hasColumn('page_indexing_statuses', 'last_reindex_requested_at')) {
                $table->timestamp('last_reindex_requested_at')->nullable()->after('page');
            }
            if (! Schema::hasColumn('page_indexing_statuses', 'last_google_status_checked_at')) {
                $table->timestamp('last_google_status_checked_at')->nullable()->after('last_reindex_requested_at');
            }
            if (! Schema::hasColumn('page_indexing_statuses', 'google_verdict')) {
                $table->string('google_verdict')->nullable()->after('last_google_status_checked_at');
            }
            if (! Schema::hasColumn('page_indexing_statuses', 'google_coverage_state')) {
                $table->string('google_coverage_state')->nullable()->after('google_verdict');
            }
            if (! Schema::hasColumn('page_indexing_statuses', 'google_indexing_state')) {
                $table->string('google_indexing_state')->nullable()->after('google_coverage_state');
            }
            if (! Schema::hasColumn('page_indexing_statuses', 'google_last_crawl_at')) {
                $table->timestamp('google_last_crawl_at')->nullable()->after('google_indexing_state');
            }
            if (! Schema::hasColumn('page_indexing_statuses', 'google_status_payload')) {
                $table->json('google_status_payload')->nullable()->after('google_last_crawl_at');
            }
        });

        if (Schema::hasColumn('page_indexing_statuses', 'last_indexed_at')
            && Schema::hasColumn('page_indexing_statuses', 'last_reindex_requested_at')) {
            DB::table('page_indexing_statuses')
                ->whereNull('last_reindex_requested_at')
                ->update(['last_reindex_requested_at' => DB::raw('last_indexed_at')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('page_indexing_statuses')) {
            return;
        }

        Schema::table('page_indexing_statuses', function (Blueprint $table) {
            if (Schema::hasColumn('page_indexing_statuses', 'google_status_payload')) {
                $table->dropColumn('google_status_payload');
            }
            if (Schema::hasColumn('page_indexing_statuses', 'google_last_crawl_at')) {
                $table->dropColumn('google_last_crawl_at');
            }
            if (Schema::hasColumn('page_indexing_statuses', 'google_indexing_state')) {
                $table->dropColumn('google_indexing_state');
            }
            if (Schema::hasColumn('page_indexing_statuses', 'google_coverage_state')) {
                $table->dropColumn('google_coverage_state');
            }
            if (Schema::hasColumn('page_indexing_statuses', 'google_verdict')) {
                $table->dropColumn('google_verdict');
            }
            if (Schema::hasColumn('page_indexing_statuses', 'last_google_status_checked_at')) {
                $table->dropColumn('last_google_status_checked_at');
            }
            if (Schema::hasColumn('page_indexing_statuses', 'last_reindex_requested_at')) {
                $table->dropColumn('last_reindex_requested_at');
            }
        });
    }
};
