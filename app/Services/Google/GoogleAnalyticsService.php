<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\DateRange;
use Google\Service\AnalyticsData\Dimension;
use Google\Service\AnalyticsData\Metric;
use Google\Service\AnalyticsData\RunReportRequest;
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchDailyTraffic(GoogleAccount $account, string $propertyId, string $startDate, string $endDate): array
    {
        $client = $this->clientFactory->make($account);
        $data = new AnalyticsData($client);

        $request = new RunReportRequest();
        $request->setDateRanges([
            new DateRange(['startDate' => $startDate, 'endDate' => $endDate]),
        ]);
        $request->setDimensions([
            new Dimension(['name' => 'date']),
            new Dimension(['name' => 'sessionSource']),
        ]);
        $request->setMetrics([
            new Metric(['name' => 'totalUsers']),
            new Metric(['name' => 'sessions']),
            new Metric(['name' => 'bounceRate']),
        ]);
        $request->setLimit(10000);

        $response = $data->properties->runReport($propertyId, $request);

        $rows = [];
        $now = now()->toDateTimeString();

        foreach ($response->getRows() ?? [] as $row) {
            $dims = $row->getDimensionValues();
            $metrics = $row->getMetricValues();

            $dateStr = substr($dims[0]->getValue(), 0, 4).'-'
                .substr($dims[0]->getValue(), 4, 2).'-'
                .substr($dims[0]->getValue(), 6, 2);

            $rows[] = [
                'date' => $dateStr,
                'source' => $dims[1]->getValue() ?: '(direct)',
                'users' => (int) ($metrics[0]->getValue() ?? 0),
                'sessions' => (int) ($metrics[1]->getValue() ?? 0),
                'bounce_rate' => round((float) ($metrics[2]->getValue() ?? 0) * 100, 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
