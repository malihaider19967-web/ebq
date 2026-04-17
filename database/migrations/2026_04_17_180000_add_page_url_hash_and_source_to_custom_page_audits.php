<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_page_audits', function (Blueprint $table) {
            $table->char('page_url_hash', 64)->nullable()->after('page_url');
            $table->string('source', 32)->default('custom')->after('user_id');
        });

        DB::table('custom_page_audits')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $url = (string) $row->page_url;
                DB::table('custom_page_audits')->where('id', $row->id)->update([
                    'page_url_hash' => hash('sha256', $url),
                ]);
            }
        });

        Schema::table('custom_page_audits', function (Blueprint $table) {
            $table->index(['website_id', 'page_url_hash'], 'custom_page_audits_website_page_hash_index');
        });
    }

    public function down(): void
    {
        Schema::table('custom_page_audits', function (Blueprint $table) {
            $table->dropIndex('custom_page_audits_website_page_hash_index');
            $table->dropColumn(['page_url_hash', 'source']);
        });
    }
};
