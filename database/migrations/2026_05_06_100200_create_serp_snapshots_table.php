<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per SERP fetch (provider response). `fetched_on` is the date part
 * of fetched_at, written by the ingestion service so we can enforce a daily
 * unique without functional indexes (MySQL portability).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('serp_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->string('device', 16)->default('desktop');
            $table->string('country', 16)->default('us');
            $table->string('location', 128)->nullable();
            $table->string('provider', 32)->default('serper');
            $table->dateTime('fetched_at');
            $table->date('fetched_on');
            $table->string('raw_payload_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['keyword_id', 'device', 'country', 'location', 'fetched_on'],
                'serp_snapshots_daily_unique'
            );
            $table->index(['keyword_id', 'fetched_at']);
            $table->index(['raw_payload_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serp_snapshots');
    }
};
