<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log of crawl-issue summary reports sent from the admin Marketing panel:
 * which website, which admin sent it, to whom, and a snapshot of the numbers +
 * example errors that were in the email at send time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_report_sends', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('website_id')->nullable()->constrained()->nullOnDelete();
            // The client this report concerns (website owner), if known.
            $table->foreignUlid('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            // The admin who pressed "Send".
            $table->foreignUlid('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_email');            // actual address the email went to
            $table->string('subject', 512);
            $table->json('summary');               // snapshot: counts + health + 3 examples
            $table->string('status', 16)->default('sent'); // sent | failed
            $table->timestamps();

            $table->index(['website_id', 'created_at']);
            $table->index('to_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_report_sends');
    }
};
