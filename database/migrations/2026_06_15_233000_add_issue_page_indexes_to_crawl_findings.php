<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Covering indexes for the paginated Issue Detail page (SiteIssues / issues.show).
 * The page filters open findings by website + category (+ optional type) and
 * orders by impact desc. Without these the default category view filesorts every
 * row (166k on a large site → ~20s page load). The two indexes make both the
 * COUNT and the ordered LIMIT index-served:
 *   - (website_id, category, status, impact)        → default category view + count
 *   - (website_id, category, status, type, impact)  → type-filtered view + typeCounts GROUP BY
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_findings', function (Blueprint $table): void {
            $table->index(['website_id', 'category', 'status', 'impact'], 'crawl_findings_issue_default_idx');
            $table->index(['website_id', 'category', 'status', 'type', 'impact'], 'crawl_findings_issue_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_findings', function (Blueprint $table): void {
            $table->dropIndex('crawl_findings_issue_default_idx');
            $table->dropIndex('crawl_findings_issue_type_idx');
        });
    }
};
