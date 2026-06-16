<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anonymous, no-signup keyword volume checks run from the public marketing
     * site. Mirrors guest_rank_checks — identified only by an unguessable
     * token, no website/user. The actual volume data lives in the shared
     * keyword_metrics cache; this row just holds the request + a snapshot of
     * the result for the public report page. Additive; safe under
     * `migrate --force`.
     */
    public function up(): void
    {
        Schema::create('guest_keyword_volumes', function (Blueprint $table) {
            $table->id();
            $table->char('token', 36)->unique();
            $table->string('keyword', 200);
            $table->string('country', 8)->default('global');
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
        Schema::dropIfExists('guest_keyword_volumes');
    }
};
