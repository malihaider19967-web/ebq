<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the connected Google account's email so the source pickers
     * can label which login owns each GA property / GSC site (a user may
     * connect several accounts). Nullable + additive; backfilled lazily
     * on the next OAuth refresh for pre-existing rows.
     */
    public function up(): void
    {
        Schema::table('google_accounts', function (Blueprint $table) {
            $table->string('email')->nullable()->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('google_accounts', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
