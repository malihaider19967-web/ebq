<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-organic SERP enrichments — PAA, related searches, knowledge panel,
 * sitelinks, etc. One row per feature instance per snapshot; `payload`
 * holds the provider-shaped JSON for the feature.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('serp_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('serp_snapshots')->cascadeOnDelete();
            $table->string('feature_type', 32);
            $table->json('payload');
            $table->timestamps();

            $table->index(['snapshot_id', 'feature_type'], 'serp_features_snapshot_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serp_features');
    }
};
