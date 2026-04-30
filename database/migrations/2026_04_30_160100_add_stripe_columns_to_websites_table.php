<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe / Cashier columns on `websites`.
 *
 * Adds the per-billable model columns that Laravel Cashier expects when
 * the `Billable` trait is mixed in. We pick the Website model (not User)
 * because tier is per-website today — a single user can own multiple
 * websites at different plan levels.
 *
 * - `stripe_id`: customer ID at Stripe (null until first checkout).
 * - `pm_type`: card brand (visa / mastercard / …) for the default
 *   payment method.
 * - `pm_last_four`: last 4 digits of the default card.
 * - `trial_ends_at`: Cashier-managed trial end timestamp.
 *
 * Cashier's standard `subscriptions` and `subscription_items` tables
 * are published via `php artisan vendor:publish --tag="cashier-migrations"`
 * and run separately — those carry the actual subscription rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->string('stripe_id', 191)->nullable()->index()->after('tier');
            $table->string('pm_type', 32)->nullable()->after('stripe_id');
            $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            $table->timestamp('trial_ends_at')->nullable()->after('pm_last_four');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }
};
