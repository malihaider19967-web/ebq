<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_user', function (Blueprint $table) {
            // Pivot rows are written via belongsToMany attach()/sync(), which do
            // not generate ULIDs; the surrogate PK is never FK-referenced, so it
            // stays an auto-increment bigint. The two foreign keys ARE ULID.
            $table->id();
            $table->foreignUlid('website_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['website_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_user');
    }
};
