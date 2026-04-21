<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->timestamp('last_traffic_drop_alert_at')->nullable()->after('last_analytics_sync_at');
            $table->timestamp('last_rank_drop_alert_at')->nullable()->after('last_traffic_drop_alert_at');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn(['last_traffic_drop_alert_at', 'last_rank_drop_alert_at']);
        });
    }
};
