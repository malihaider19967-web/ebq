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
        Schema::create('search_console_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('query');
            $table->string('page');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('position', 8, 2)->default(0);
            $table->string('country', 10)->default('');
            $table->string('device', 20)->default('');
            $table->decimal('ctr', 8, 4)->default(0);
            $table->timestamps();

            $table->unique(['website_id', 'date', 'query', 'page', 'country', 'device'], 'sc_unique');
            $table->index(['website_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_console_data');
    }
};
