<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_page_audits', function (Blueprint $table) {
            $table->id();
            // Unguessable public handle used in the status/results URLs. No
            // website_id / user_id — these are anonymous landing-page audits.
            $table->char('token', 36)->unique();
            $table->string('url', 700);
            $table->string('keyword', 200);
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->string('primary_keyword', 200)->nullable();
            $table->string('primary_keyword_source', 32)->nullable();
            // Abuse tracking / rate-limit forensics; email reserved for a
            // future soft lead-capture (unused at launch).
            $table->string('ip', 45)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_page_audits');
    }
};
