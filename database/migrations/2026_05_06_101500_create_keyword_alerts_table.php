<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-website research alerts emitted by DetectResearchSignalsJob. Four
 * types: ranking_drop, new_opportunity, serp_change, volatility_spike.
 * Generalises the existing traffic-drop alerting pattern.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained('keywords')->nullOnDelete();
            $table->string('type', 32);
            $table->string('severity', 16)->default('info');
            $table->json('payload')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'type', 'created_at'], 'keyword_alerts_site_type_idx');
            $table->index(['acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_alerts');
    }
};
