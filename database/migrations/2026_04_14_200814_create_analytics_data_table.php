<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analytics_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->string('source');
            $table->decimal('bounce_rate', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'date', 'source']);
            $table->index(['website_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_data');
    }
};
