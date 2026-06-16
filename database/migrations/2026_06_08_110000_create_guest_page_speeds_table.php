<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anonymous, no-signup PageSpeed tests run from the public marketing
     * site. Mirrors guest_page_audits — identified only by an unguessable
     * token, no website/user. Additive; safe under `migrate --force`.
     */
    public function up(): void
    {
        Schema::create('guest_page_speeds', function (Blueprint $table) {
            $table->id();
            $table->char('token', 36)->unique();
            $table->string('url', 700);
            $table->string('status', 16)->default('queued');
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_page_speeds');
    }
};
