<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user overlay on the SHARED crawl_findings. A finding is a domain-level fact
 * (stored once on the crawl_site), but each subscriber can independently
 * ignore/resolve it. Absence of a row = 'open' (the default). Impact + the
 * click-dependent severity escalation are computed read-time from each website's
 * own SearchConsoleData, so they are NOT stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_finding_states', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('website_id')->constrained('websites')->cascadeOnDelete();
            $table->foreignUlid('finding_id')->constrained('crawl_findings')->cascadeOnDelete();
            $table->string('status', 12)->default('open'); // open|ignored|resolved
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'finding_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_finding_states');
    }
};
