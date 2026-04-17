<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_audit_reports', function (Blueprint $table) {
            $table->string('primary_keyword', 200)->nullable()->after('page_hash');
            $table->string('primary_keyword_source', 32)->nullable()->after('primary_keyword');
        });
    }

    public function down(): void
    {
        Schema::table('page_audit_reports', function (Blueprint $table) {
            $table->dropColumn(['primary_keyword', 'primary_keyword_source']);
        });
    }
};
