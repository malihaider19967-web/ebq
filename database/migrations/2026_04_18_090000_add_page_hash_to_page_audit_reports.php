<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_audit_reports', function (Blueprint $table) {
            $table->char('page_hash', 64)->nullable()->after('page');
        });

        DB::table('page_audit_reports')
            ->whereNull('page_hash')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('page_audit_reports')
                        ->where('id', $row->id)
                        ->update(['page_hash' => hash('sha256', (string) $row->page)]);
                }
            });

        Schema::table('page_audit_reports', function (Blueprint $table) {
            $table->dropUnique(['website_id', 'page']);
            $table->char('page_hash', 64)->nullable(false)->change();
            $table->unique(['website_id', 'page_hash'], 'page_audit_reports_website_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('page_audit_reports', function (Blueprint $table) {
            $table->dropUnique('page_audit_reports_website_hash_unique');
            $table->unique(['website_id', 'page']);
            $table->dropColumn('page_hash');
        });
    }
};
