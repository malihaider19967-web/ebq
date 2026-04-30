<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans table — single source of truth for what's exposed
 * on /pricing, the public /api/v1/plans endpoint that drives the
 * WordPress plugin's setup wizard, and the billing checkout flow.
 *
 * `slug` is the immutable public identifier (free, starter, pro, agency)
 * matching what the marketing pricing page uses today. `stripe_price_id_*`
 * holds the Stripe price IDs once Stripe products are configured —
 * nullable because Free has no Stripe price and we may roll out before
 * the Stripe products exist.
 *
 * `features` is JSON-stringified bullet list rendered in marketing UI.
 * `display_order` controls left-to-right card layout. `is_active` lets
 * us deprecate plans without deleting historical subscription rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 64);
            $table->string('tagline', 191)->nullable();
            $table->integer('price_monthly_usd')->default(0);
            $table->integer('price_yearly_usd')->default(0);
            $table->string('stripe_price_id_monthly', 128)->nullable();
            $table->string('stripe_price_id_yearly', 128)->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_highlighted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
