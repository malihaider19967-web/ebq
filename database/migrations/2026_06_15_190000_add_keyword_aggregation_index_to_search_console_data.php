<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Keywords page aggregates GSC data per query (GROUP BY query, ORDER BY
 * SUM(clicks)). The existing (website_id, date, query) index can't group by
 * query (date precedes it), so the query did a temp-table + filesort over
 * ~430k rows (~25s → page timeout for high-volume sites).
 *
 * This covering index puts query right after website_id and includes the
 * aggregated columns, so the GROUP BY streams from the index (no temp table,
 * no row lookups). Added online (MariaDB INPLACE / LOCK=NONE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_console_data', function (Blueprint $table): void {
            $table->index(
                ['website_id', 'query', 'clicks', 'impressions', 'ctr', 'position'],
                'scd_wid_query_agg',
            );
        });
    }

    public function down(): void
    {
        Schema::table('search_console_data', function (Blueprint $table): void {
            $table->dropIndex('scd_wid_query_agg');
        });
    }
};
