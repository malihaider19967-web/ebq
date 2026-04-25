<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a denormalised `units_consumed` column to `client_activities` so the
 * admin "API Usage" page can SUM(units_consumed) WHERE provider IN (...)
 * without parsing the `meta` JSON on every read. For Keywords Everywhere
 * this is the keyword count of the request (each keyword costs 1 credit);
 * for SERP API it's 1 (each call is 1 credit). Null for non-billable rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_activities', function (Blueprint $table): void {
            $table->unsignedInteger('units_consumed')->nullable()->after('meta');
            $table->index(['provider', 'created_at', 'user_id'], 'client_activities_provider_user_idx');
            $table->index(['provider', 'created_at', 'website_id'], 'client_activities_provider_website_idx');
        });
    }

    public function down(): void
    {
        Schema::table('client_activities', function (Blueprint $table): void {
            $table->dropIndex('client_activities_provider_user_idx');
            $table->dropIndex('client_activities_provider_website_idx');
            $table->dropColumn('units_consumed');
        });
    }
};
