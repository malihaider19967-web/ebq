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
     * List the sitemaps Google Search Console knows about for a property.
     *
     * Read-only (needs only the webmasters.readonly scope the app already
     * requests). Each entry mirrors the WmxSitemap fields plus the rolled-up
     * submitted/indexed URL counts from its contents.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSitemaps(GoogleAccount $account, string $siteUrl): array
    {
        $client = $this->clientFactory->make($account);
        $sc = new SearchConsole($client);

        $out = [];

        foreach ($sc->sitemaps->listSitemaps($siteUrl)->getSitemap() ?? [] as $sitemap) {
            $submitted = null;
            $indexed = null;
            foreach ($sitemap->getContents() ?? [] as $content) {
                $submitted = (int) $submitted + (int) $content->getSubmitted();
                $indexed = (int) $indexed + (int) $content->getIndexed();
            }

            $out[] = [
                'path' => (string) $sitemap->getPath(),
                'type' => $sitemap->getType() ? (string) $sitemap->getType() : null,
                'is_pending' => (bool) $sitemap->getIsPending(),
                'is_sitemaps_index' => (bool) $sitemap->getIsSitemapsIndex(),
                'errors' => (int) $sitemap->getErrors(),
                'warnings' => (int) $sitemap->getWarnings(),
                'submitted_urls' => $submitted,
                'indexed_urls' => $indexed,
                'last_submitted' => $sitemap->getLastSubmitted(),
                'last_downloaded' => $sitemap->getLastDownloaded(),
            ];
        }

        return $out;
    }

    /**
     * Fetch a single date window (keep to <=7 days for performance).
     *
     * Requests the full dimension set (date, query, page, country, device) so
     * downstream reports can filter per market and per device. GSC caps each
     * response at 25,000 rows; when we hit that cap we paginate via startRow
     * until either fewer rows come back or we reach the safety ceiling.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchSearchAnalytics(GoogleAccount $account, string $siteUrl, string $startDate, string $endDate): array
    {
        $client = $this->clientFactory->make($account);
        $sc = new SearchConsole($client);

        $rows = [];
        $now = now()->toDateTimeString();
        $startRow = 0;
        $pageSize = 25000;
        $safetyCeiling = 200000; // absolute max rows per batch to avoid runaway loops

        while (true) {
            $request = new SearchAnalyticsQueryRequest();
            $request->setStartDate($startDate);
            $request->setEndDate($endDate);
            $request->setDimensions(['date', 'query', 'page', 'country', 'device']);
            $request->setRowLimit($pageSize);
            $request->setStartRow($startRow);

            $response = $sc->searchanalytics->query($siteUrl, $request);
            $page = $response->getRows() ?? [];
            $pageCount = count($page);

            foreach ($page as $row) {
                $keys = $row->getKeys();
                $rows[] = [
                    'date' => $keys[0] ?? '',
                    'query' => $keys[1] ?? '',
                    'page' => $keys[2] ?? '',
                    'country' => strtoupper((string) ($keys[3] ?? '')),
                    'device' => strtoupper((string) ($keys[4] ?? '')),
                    'clicks' => (int) $row->getClicks(),
                    'impressions' => (int) $row->getImpressions(),
                    'ctr' => round((float) $row->getCtr(), 4),
                    'position' => round((float) $row->getPosition(), 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($pageCount < $pageSize) {
                break; // last page
            }
            $startRow += $pageSize;
            if ($startRow >= $safetyCeiling) {
                break; // safety — GSC is returning unusually large batches, bail
            }
        }

        return $rows;
    }
}
