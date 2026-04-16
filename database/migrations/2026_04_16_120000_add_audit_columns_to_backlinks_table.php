<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backlinks', function (Blueprint $table) {
            $table->string('audit_status', 32)->nullable()->after('is_dofollow');
            $table->timestamp('audit_checked_at')->nullable()->after('audit_status');
            $table->json('audit_result')->nullable()->after('audit_checked_at');

            $table->index(['website_id', 'audit_status']);
        });
    }

    public function down(): void
    {
        Schema::table('backlinks', function (Blueprint $table) {
            $table->dropIndex(['website_id', 'audit_status']);
            $table->dropColumn(['audit_status', 'audit_checked_at', 'audit_result']);
        });
    }
};
