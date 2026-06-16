<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The self-hosted Keyword Planner API returns a top-of-page bid RANGE
 * (low/high) rather than the single CPC value Keywords Everywhere gives. Store
 * both endpoints so the keyword tools can show the spread; the existing `cpc`
 * column keeps a representative value (we use the high bid) for back-compat.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('keyword_metrics', function (Blueprint $table): void {
            $table->decimal('low_top_of_page_bid', 10, 4)->nullable()->after('cpc');
            $table->decimal('high_top_of_page_bid', 10, 4)->nullable()->after('low_top_of_page_bid');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_metrics', function (Blueprint $table): void {
            $table->dropColumn(['low_top_of_page_bid', 'high_top_of_page_bid']);
        });
    }
};
