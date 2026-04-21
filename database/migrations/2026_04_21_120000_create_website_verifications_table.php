<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('challenge_code', 80);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['website_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_verifications');
    }
};
