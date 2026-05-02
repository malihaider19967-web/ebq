<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan-controlled website limit.
 *
 * Each plan caps how many websites a user on that plan can manage.
 * `max_websites` nullable = unlimited (Agency is the canonical
 * unlimited case). Backfill matches the current /pricing copy:
 *   Free=1, Starter=1, Pro=5, Agency=null (unlimited).
 *
 * The admin Plans editor exposes this so non-engineers can adjust the
 * caps without a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_websites')->nullable()->after('trial_days');
        });

        // Seed reasonable defaults to match the existing pricing copy.
        // Existing plan rows on a fresh-seeded install pick these up;
        // admins can edit later via the Plans admin UI.
        \DB::table('plans')->where('slug', 'free')->update(['max_websites' => 1]);
        \DB::table('plans')->where('slug', 'starter')->update(['max_websites' => 1]);
        \DB::table('plans')->where('slug', 'pro')->update(['max_websites' => 5]);
        \DB::table('plans')->where('slug', 'agency')->update(['max_websites' => null]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_websites');
        });
    }
};
