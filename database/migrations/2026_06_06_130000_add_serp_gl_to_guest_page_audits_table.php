<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_page_audits', function (Blueprint $table) {
            // Visitor-chosen Google SERP country (gl) for the benchmark; null = auto-detect from page locale.
            $table->char('serp_gl', 2)->nullable()->after('keyword');
        });
    }

    public function down(): void
    {
        Schema::table('guest_page_audits', function (Blueprint $table) {
            $table->dropColumn('serp_gl');
        });
    }
};
