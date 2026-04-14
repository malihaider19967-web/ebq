<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;

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

    /**
     * Fetch a single date window (keep to <=7 days for performance).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchSearchAnalytics(GoogleAccount $account, string $siteUrl, string $startDate, string $endDate): array
    {
        $client = $this->clientFactory->make($account);
        $sc = new SearchConsole($client);

        $request = new SearchAnalyticsQueryRequest();
        $request->setStartDate($startDate);
        $request->setEndDate($endDate);
        $request->setDimensions(['date', 'query', 'page']);
        $request->setRowLimit(5000);

        $response = $sc->searchanalytics->query($siteUrl, $request);

        $rows = [];
        $now = now()->toDateTimeString();

        foreach ($response->getRows() ?? [] as $row) {
            $keys = $row->getKeys();

            $rows[] = [
                'date' => $keys[0],
                'query' => $keys[1],
                'page' => $keys[2],
                'country' => '',
                'device' => '',
                'clicks' => (int) $row->getClicks(),
                'impressions' => (int) $row->getImpressions(),
                'ctr' => round((float) $row->getCtr(), 4),
                'position' => round((float) $row->getPosition(), 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
