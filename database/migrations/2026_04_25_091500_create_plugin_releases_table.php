<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_releases', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->default('ebq-seo');
            $table->string('version', 40);
            $table->string('channel', 20)->default('stable');
            $table->string('status', 20)->default('draft');
            $table->text('release_notes')->nullable();
            $table->string('zip_path');
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->foreignId('rollback_of_id')->nullable()->constrained('plugin_releases')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['slug', 'version', 'channel']);
            $table->index(['slug', 'channel', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_releases');
    }
};
