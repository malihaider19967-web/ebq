<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_audit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('page', 700);
            $table->string('status')->default('completed');
            $table->timestamp('audited_at')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('page_size_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'page']);
            $table->index(['website_id', 'audited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_audit_reports');
    }
};
