<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_projects', function (Blueprint $table) {
            $table->text('custom_prompt')->nullable()->after('audience');
        });
    }

    public function down(): void
    {
        Schema::table('writer_projects', function (Blueprint $table) {
            $table->dropColumn('custom_prompt');
        });
    }
};
