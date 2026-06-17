<?php

namespace App\Support;

use App\Models\GoogleAccount;
use App\Models\User;
use App\Services\Google\GoogleAnalyticsService;
use App\Services\Google\SearchConsoleService;
use Illuminate\Support\Facades\Log;

/**
 * Pools the GA4 properties and Search Console sites a user can access
 * across ALL of their connected Google accounts, tagging each option with
 * the account it came from.
 *
 * This is what lets a user pick a GA property from one Google login and a
 * GSC site from another: every connected account contributes its own
 * properties/sites to a single flat list, and the chosen option carries
 * its `account_id` so we can persist the right per-source GoogleAccount.
 *
 * Listing is best-effort per account — a single revoked / expired account
 * is logged and skipped rather than blanking the whole pool.
 */
class GoogleSourcePool
{
    public function __construct(
        private GoogleAnalyticsService $analytics,
        private SearchConsoleService $searchConsole,
    ) {}

    /**
     * @return array{
     *   ga: array<int, array{id: string, name: string, account_id: int, account_label: string}>,
     *   gsc: array<int, array{siteUrl: string, account_id: int, account_label: string}>,
     *   accounts: array<int, array{id: int, label: string}>,
     *   ga_error: bool,
     *   gsc_error: bool
     * }
     */
    public function forUser(User $user): array
    {
        $accounts = $user->googleAccounts()->orderBy('id')->get();

        $ga = [];
        $gsc = [];
        $accountList = [];
        $gaError = false;
        $gscError = false;

        foreach ($accounts as $account) {
            $accountList[] = ['id' => (string) $account->id, 'label' => $account->label()];

            foreach ($this->propertiesFor($account, $gaError) as $prop) {
                $ga[] = [
                    'id' => (string) $prop['id'],
                    'name' => (string) $prop['name'],
                    'account_id' => (string) $account->id,
                    'account_label' => $account->label(),
                ];
            }

            foreach ($this->sitesFor($account, $gscError) as $site) {
                $gsc[] = [
                    'siteUrl' => (string) $site['siteUrl'],
                    'account_id' => (string) $account->id,
                    'account_label' => $account->label(),
                ];
            }
        }

        return [
            'ga' => $ga,
            'gsc' => $gsc,
            'accounts' => $accountList,
            'ga_error' => $gaError,
            'gsc_error' => $gscError,
        ];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function propertiesFor(GoogleAccount $account, bool &$error): array
    {
        try {
            return $this->analytics->listProperties($account);
        } catch (\Throwable $e) {
            $error = true;
            Log::warning('GoogleSourcePool: failed to list GA properties for account '.$account->id.': '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return array<int, array{siteUrl: string, permissionLevel: string}>
     */
    private function sitesFor(GoogleAccount $account, bool &$error): array
    {
        try {
            return $this->searchConsole->listSites($account);
        } catch (\Throwable $e) {
            $error = true;
            Log::warning('GoogleSourcePool: failed to list GSC sites for account '.$account->id.': '.$e->getMessage());

            return [];
        }
    }
}
