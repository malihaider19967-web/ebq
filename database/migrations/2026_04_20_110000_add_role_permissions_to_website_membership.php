<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_user', function (Blueprint $table) {
            $table->string('role', 32)->default('member')->after('user_id');
            $table->json('permissions')->nullable()->after('role');
        });

        Schema::table('website_invitations', function (Blueprint $table) {
            $table->string('role', 32)->default('member')->after('email');
            $table->json('permissions')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('website_user', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions']);
        });

        Schema::table('website_invitations', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions']);
        });
    }
};
