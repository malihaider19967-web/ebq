<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier columns + plan snapshot, now on the User.
 *
 * Cashier reads stripe_id / pm_type / pm_last_four / trial_ends_at off
 * the billable model. Adding `current_plan_slug` alongside is a snapshot
 * of "what plan is this user on right now" — derived from their active
 * subscription's stripe_price → Plan lookup. Snapshotted because every
 * read-path (website limit checks, frozen-site decisions, dashboard
 * tier badges) needs it on the hot path; computing live every time
 * would join through `subscriptions` + `plans` per request.
 *
 * The webhook handler keeps `current_plan_slug` in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index()->after('remember_token');
            $table->string('pm_type')->nullable()->after('stripe_id');
            $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            $table->timestamp('trial_ends_at')->nullable()->after('pm_last_four');
            $table->string('current_plan_slug', 32)->nullable()->index()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_id',
                'pm_type',
                'pm_last_four',
                'trial_ends_at',
                'current_plan_slug',
            ]);
        });
    }
};
