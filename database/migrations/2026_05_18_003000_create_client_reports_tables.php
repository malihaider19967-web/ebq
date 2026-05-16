<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * White-label client reports (Phase 10) persistence:
 *
 *   client_report_brands     — per-website branding (logo, colours,
 *                              sender, footer).
 *   client_report_schedules  — cadence + recipients.
 *   client_reports           — history of rendered PDF + email sends.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_report_brands', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('website_id')->unique();
            $t->string('logo_url', 500)->nullable();
            $t->string('primary_color', 16)->nullable();
            $t->string('accent_color', 16)->nullable();
            $t->string('sender_name', 100)->nullable();
            $t->string('sender_email', 200)->nullable();
            $t->string('footer_note', 1000)->nullable();
            $t->string('frequency', 16)->default('off'); // off | weekly | monthly
            $t->text('recipients')->nullable();           // comma-separated emails
            $t->timestamps();
        });

        Schema::create('client_report_schedules', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('website_id');
            $t->string('frequency', 16);
            $t->timestamp('next_run_at')->nullable();
            $t->timestamps();
            $t->index('website_id');
            $t->index('next_run_at');
        });

        Schema::create('client_reports', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('website_id');
            $t->string('status', 16)->default('queued'); // queued | sent | failed
            $t->string('pdf_url', 500)->nullable();
            $t->text('recipients')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index('website_id');
            $t->index(['website_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_reports');
        Schema::dropIfExists('client_report_schedules');
        Schema::dropIfExists('client_report_brands');
    }
};
