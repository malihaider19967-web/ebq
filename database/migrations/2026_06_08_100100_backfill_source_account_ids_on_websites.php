<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Preserve today's behavior: before this change every website synced
     * via `googleAccounts()->latest()->first()`. Point both new source FKs
     * at the user's most-recent Google account so existing websites keep
     * working unchanged.
     *
     * Done with raw `DB::table` updates (NOT Eloquent) so the
     * `Website::created` hook never fires and no 365-day sync jobs are
     * dispatched across the production fleet. Only fills NULLs, so it's
     * idempotent and additive.
     */
    public function up(): void
    {
        // Pick the latest account per user (max id ≈ most recently connected).
        $latestByUser = DB::table('google_accounts')
            ->selectRaw('user_id, MAX(id) as account_id')
            ->groupBy('user_id')
            ->pluck('account_id', 'user_id');

        foreach ($latestByUser as $userId => $accountId) {
            DB::table('websites')
                ->where('user_id', $userId)
                ->whereNull('ga_google_account_id')
                ->update(['ga_google_account_id' => $accountId]);

            DB::table('websites')
                ->where('user_id', $userId)
                ->whereNull('gsc_google_account_id')
                ->update(['gsc_google_account_id' => $accountId]);
        }
    }

    public function down(): void
    {
        // Non-destructive: leave the backfilled values in place.
    }
};
