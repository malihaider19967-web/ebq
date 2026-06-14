<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tie each data source (GA / GSC) to a SPECIFIC Google account so that:
     *   - GA can come from one Google login and GSC from another, and
     *   - either source can be absent (degraded onboarding / reports).
     *
     * Both columns are nullable; `nullOnDelete` so deleting a GoogleAccount
     * degrades the website (FK → null) rather than cascading the delete.
     * Purely additive — safe under `migrate --force` on the production DB.
     */
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->foreignId('ga_google_account_id')->nullable()->after('ga_property_id')
                ->constrained('google_accounts')->nullOnDelete();
            $table->foreignId('gsc_google_account_id')->nullable()->after('gsc_site_url')
                ->constrained('google_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ga_google_account_id');
            $table->dropConstrainedForeignId('gsc_google_account_id');
        });
    }
};
