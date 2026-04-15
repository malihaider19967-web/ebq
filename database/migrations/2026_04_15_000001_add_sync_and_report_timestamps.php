<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->timestamp('last_analytics_sync_at')->nullable();
            $table->timestamp('last_search_console_sync_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_growth_report_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn([
                'last_analytics_sync_at',
                'last_search_console_sync_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_growth_report_sent_at');
        });
    }
};
