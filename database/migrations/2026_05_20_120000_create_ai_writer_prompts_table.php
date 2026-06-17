<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_writer_prompts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->uuid('external_id')->unique();

            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();

            $table->string('title', 200);
            $table->text('body');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_writer_prompts');
    }
};
