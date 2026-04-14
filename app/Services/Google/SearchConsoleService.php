<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;
use Google\Client as GoogleClient;
use Google\Service\SearchConsole;

class SearchConsoleService
{
    public function __construct(private GoogleClientFactory $clientFactory)
    {
    }

    /**
     * @return array<int, array{siteUrl: string, permissionLevel: string}>
     */
    public function listSites(GoogleAccount $account): array
    {
        $client = $this->clientFactory->make($account);
        $sc = new SearchConsole($client);

        $sites = [];

        foreach ($sc->sites->listSites()->getSiteEntry() ?? [] as $site) {
            $sites[] = [
                'siteUrl' => $site->getSiteUrl(),
                'permissionLevel' => $site->getPermissionLevel(),
            ];
        }

        return $sites;
    }

    public function fetchSearchAnalytics(string $siteUrl, string $startDate, string $endDate): array
    {
        return [];
    }
}
