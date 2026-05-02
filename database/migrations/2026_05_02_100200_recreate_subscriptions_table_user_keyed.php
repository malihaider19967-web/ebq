<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions are now keyed on `user_id` instead of `website_id`.
 *
 * Per-user billing means one Cashier subscription per user; the
 * existing `subscriptions` table (and its `subscription_items`)
 * is rebuilt to match. The user confirmed there are no paying
 * customers yet — drop and recreate is the simplest path. Cashier's
 * default schema is preserved verbatim except for the FK column name.
 *
 * This migration must run AFTER both
 *   - drop_billing_columns_from_websites_table
 *   - add_billing_columns_to_users_table
 * because Cashier's first request after the swap reads from `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            // Cashier's Billable trait derives the FK from the model
            // class name (User → user_id). Keeping the FK constrained
            // is fine here because users are never hard-deleted; if
            // they ever are, Stripe still owns the source of truth
            // and we'd rebuild from webhooks.
            $table->foreignId('user_id');
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'stripe_status']);
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id');
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->integer('quantity')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'stripe_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
    }
};
