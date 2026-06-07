<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_page_audits', function (Blueprint $table) {
            // Captured alongside the email on the 2nd (email-gated) audit.
            $table->string('name')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('guest_page_audits', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
