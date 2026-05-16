<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Slug rename: starter → pro, old pro → startup.
 *
 * Rationale: the marketing tier "Starter" is being repositioned as the
 * entry-level paid tier and renamed to "Pro". The old "Pro" tier (5
 * sites, AI Writer, …) becomes "Startup". Agency and Free are unchanged.
 *
 * Result order: free < pro < startup < agency.
 *
 * Order of UPDATE statements is critical to avoid a UNIQUE collision
 * on `plans.slug`:
 *
 *   1. pro     → startup   (frees the 'pro' slot)
 *   2. starter → pro       (moves into the freed slot)
 *
 * Affected tables:
 *   - plans                            (slug column)
 *   - users.current_plan_slug          (snapshot of active subscription)
 *   - settings rows that store slugs   (none in production today, but
 *                                       we still scan defensively)
 *
 * NOT touched:
 *   - Cashier `subscriptions` table — keyed by Stripe price IDs, not
 *     slugs. `User::effectivePlan()` resolves via stripe_price_id_yearly
 *     first, then falls back to current_plan_slug. The slug update on
 *     `users` keeps the fallback path correct.
 *
 * Down direction is intentionally lossy (we'd need a journal of which
 * rows we touched). This is a one-shot rename so the down() is best-
 * effort and only safe immediately after a fresh up() on the same DB.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            // 1) plans.slug — two-step to dodge the unique collision.
            DB::table('plans')->where('slug', 'pro')->update(['slug' => 'startup']);
            DB::table('plans')->where('slug', 'starter')->update(['slug' => 'pro']);

            // 2) users.current_plan_slug — same two-step.
            if (Schema::hasColumn('users', 'current_plan_slug')) {
                DB::table('users')->where('current_plan_slug', 'pro')->update(['current_plan_slug' => 'startup']);
                DB::table('users')->where('current_plan_slug', 'starter')->update(['current_plan_slug' => 'pro']);
            }

            // 3) Settings rows that happen to embed a plan slug. The
            //    `settings` table uses `key` as its primary key (no
            //    surrogate `id` column) with a JSON `value`. We rewrite
            //    any value that's a bare slug string. Nested JSON
            //    references aren't auto-rewritten — operators must edit
            //    those manually if they exist.
            if (Schema::hasTable('settings')) {
                $rows = DB::table('settings')->get(['key', 'value']);
                foreach ($rows as $row) {
                    $decoded = is_string($row->value) ? json_decode($row->value, true) : null;
                    if ($decoded === 'pro') {
                        DB::table('settings')->where('key', $row->key)->update(['value' => json_encode('startup')]);
                    } elseif ($decoded === 'starter') {
                        DB::table('settings')->where('key', $row->key)->update(['value' => json_encode('pro')]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Reverse two-step: pro → starter first, then startup → pro.
            DB::table('plans')->where('slug', 'pro')->update(['slug' => 'starter']);
            DB::table('plans')->where('slug', 'startup')->update(['slug' => 'pro']);

            if (Schema::hasColumn('users', 'current_plan_slug')) {
                DB::table('users')->where('current_plan_slug', 'pro')->update(['current_plan_slug' => 'starter']);
                DB::table('users')->where('current_plan_slug', 'startup')->update(['current_plan_slug' => 'pro']);
            }
        });
    }
};
