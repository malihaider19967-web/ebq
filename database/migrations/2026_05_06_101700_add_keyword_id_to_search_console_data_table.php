<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable `keyword_id` FK so the GSC fact table can be joined to
 * the new `keywords` dimension without re-resolving the (query, country)
 * tuple every read. ResearchBackfill / MapGscQueriesToKeywordsJob populate
 * it; nullable for safety.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('search_console_data', function (Blueprint $table): void {
            $table->foreignId('keyword_id')
                ->nullable()
                ->after('country')
                ->constrained('keywords')
                ->nullOnDelete();

            $table->index(['keyword_id', 'date'], 'scd_keyword_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('search_console_data', function (Blueprint $table): void {
            $table->dropIndex('scd_keyword_date_idx');
            $table->dropForeign(['keyword_id']);
            $table->dropColumn('keyword_id');
        });
    }
};
