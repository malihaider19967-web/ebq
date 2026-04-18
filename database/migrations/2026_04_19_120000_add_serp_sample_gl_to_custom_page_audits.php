<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_page_audits', function (Blueprint $table) {
            $table->string('serp_sample_gl', 2)->nullable()->after('target_keyword');
        });
    }

    public function down(): void
    {
        Schema::table('custom_page_audits', function (Blueprint $table) {
            $table->dropColumn('serp_sample_gl');
        });
    }
};
