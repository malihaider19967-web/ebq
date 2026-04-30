<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier's published migration creates `subscriptions.user_id` because
 * Cashier defaults to billing the User model. We bill the Website model
 * instead (per-website tier + per-website Stripe customer), and Cashier
 * derives the FK from the billable model class — so it queries
 * `subscriptions.website_id`. Rename the column + index to match.
 *
 * Why a separate migration instead of editing the original: anyone who
 * already ran `migrate` against the original Cashier-default schema will
 * have `user_id` in the database and the original migration won't re-run.
 * This forward-compatible rename works for fresh and existing installs
 * (and the matching `down()` puts it back so rollback stays clean).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rename only if the original column is still present — protects
        // fresh installs where the create-migration is amended to use
        // website_id from the start.
        if (! Schema::hasColumn('subscriptions', 'user_id')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'stripe_status']);
            $table->renameColumn('user_id', 'website_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['website_id', 'stripe_status']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('subscriptions', 'website_id')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['website_id', 'stripe_status']);
            $table->renameColumn('website_id', 'user_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'stripe_status']);
        });
    }
};
