<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rank_tracking_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('keyword', 500);
            $table->char('keyword_hash', 64);

            $table->string('target_domain');
            $table->string('target_url')->nullable();

            $table->string('search_engine', 32)->default('google');
            $table->string('search_type', 32)->default('organic');

            $table->string('country', 8)->default('us');
            $table->string('language', 16)->default('en');
            $table->string('location', 255)->nullable();

            $table->string('device', 16)->default('desktop');

            $table->unsignedSmallInteger('depth')->default(100);
            $table->string('tbs', 64)->nullable();
            $table->boolean('autocorrect')->default(true);
            $table->boolean('safe_search')->default(false);

            $table->json('competitors')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedSmallInteger('check_interval_hours')->default(12);
            $table->boolean('is_active')->default(true);

            $table->dateTime('last_checked_at')->nullable();
            $table->dateTime('next_check_at')->nullable();
            $table->string('last_status', 32)->default('pending');
            $table->text('last_error')->nullable();

            $table->unsignedInteger('current_position')->nullable();
            $table->unsignedInteger('best_position')->nullable();
            $table->unsignedInteger('initial_position')->nullable();
            $table->integer('position_change')->nullable();
            $table->string('current_url', 2048)->nullable();

            $table->timestamps();

            $table->unique(
                ['website_id', 'keyword_hash', 'search_engine', 'search_type', 'country', 'language', 'device', 'location'],
                'rtk_unique'
            );
            $table->index(['website_id', 'is_active']);
            $table->index(['next_check_at', 'is_active']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_tracking_keywords');
    }
};
