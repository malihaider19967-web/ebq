<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;
use Google\Client as GoogleClient;
use Google\Service\GoogleAnalyticsAdmin;

class GoogleAnalyticsService
{
    public function __construct(private GoogleClientFactory $clientFactory)
    {
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function listProperties(GoogleAccount $account): array
    {
        $client = $this->clientFactory->make($account);
        $admin = new GoogleAnalyticsAdmin($client);

        $properties = [];
        $pageToken = null;

        do {
            $response = $admin->accountSummaries->listAccountSummaries([
                'pageSize' => 200,
                'pageToken' => $pageToken,
            ]);

            foreach ($response->getAccountSummaries() as $summary) {
                foreach ($summary->getPropertySummaries() as $prop) {
                    $properties[] = [
                        'id' => $prop->getProperty(),
                        'name' => $prop->getDisplayName(),
                    ];
                }
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $properties;
    }

    public function fetchDailyTraffic(string $propertyId, string $startDate, string $endDate): array
    {
        return [];
    }
}
