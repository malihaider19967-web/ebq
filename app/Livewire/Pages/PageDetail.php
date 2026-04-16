<?php

namespace App\Livewire\Pages;

use App\Models\GoogleAccount;
use App\Models\PageIndexingStatus;
use App\Models\SearchConsoleData;
use App\Models\Website;
use App\Services\Google\GoogleClientFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\WithPagination;

class PageDetail extends Component
{
    use WithPagination;

    public int $websiteId = 0;
    public string $pageUrl = '';
    public string $sortBy = 'total_clicks';
    public string $sortDir = 'desc';
    public ?string $reindexMessage = null;
    public string $reindexMessageKind = 'info';
    public bool $needsGoogleReconnect = false;
    public ?string $googleStatusMessage = null;
    public string $googleStatusMessageKind = 'info';

    public function mount(string $pageUrl): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
        $this->pageUrl = urldecode($pageUrl);
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }

        $this->resetPage();
    }

    public function requestReindex(GoogleClientFactory $googleClientFactory): void
    {
        $this->reindexMessage = null;
        $this->needsGoogleReconnect = false;

        $user = Auth::user();
        if (! $user || ! $user->canViewWebsiteId($this->websiteId) || $this->pageUrl === '') {
            return;
        }

        $website = Website::query()->find($this->websiteId);
        if (! $website) {
            $this->setReindexMessage('Website not found.', 'error');

            return;
        }

        try {
            $accessToken = $this->resolveGoogleAccessToken($googleClientFactory, $user);

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post('https://indexing.googleapis.com/v3/urlNotifications:publish', [
                    'url' => $this->pageUrl,
                    'type' => 'URL_UPDATED',
                ]);

            if ($response->successful()) {
                $lastIndexedAt = data_get($response->json(), 'urlNotificationMetadata.latestUpdate.notifyTime');
                $parsedIndexedAt = is_string($lastIndexedAt) ? Carbon::parse($lastIndexedAt) : now();
                PageIndexingStatus::query()->updateOrCreate(
                    [
                        'website_id' => $this->websiteId,
                        'page' => $this->pageUrl,
                    ],
                    [
                        'last_reindex_requested_at' => $parsedIndexedAt,
                    ]
                );

                $this->setReindexMessage('Reindex request sent to Google. Processing is not guaranteed and may take time.', 'success');

                return;
            }

            $apiMessage = (string) data_get($response->json(), 'error.message', 'Google rejected the request.');
            if (str_contains(strtolower($apiMessage), 'insufficient')) {
                $apiMessage .= ' Reconnect Google to grant indexing scope.';
                $this->needsGoogleReconnect = true;
            }
            $this->setReindexMessage($apiMessage, 'error');
        } catch (\Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'insufficient')) {
                $this->needsGoogleReconnect = true;
            }
            $this->setReindexMessage($e->getMessage(), 'error');
        }
    }

    public function refreshGoogleIndexingStatus(GoogleClientFactory $googleClientFactory): void
    {
        $this->googleStatusMessage = null;
        $this->needsGoogleReconnect = false;

        $user = Auth::user();
        if (! $user || ! $user->canViewWebsiteId($this->websiteId) || $this->pageUrl === '') {
            return;
        }

        $website = Website::query()->find($this->websiteId);
        if (! $website || $website->gsc_site_url === '') {
            $this->setGoogleStatusMessage('Website or Search Console property is missing.', 'error');

            return;
        }

        try {
            $accessToken = $this->resolveGoogleAccessToken($googleClientFactory, $user);

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
                    'inspectionUrl' => $this->pageUrl,
                    'siteUrl' => $website->gsc_site_url,
                    'languageCode' => 'en-US',
                ]);

            if (! $response->successful()) {
                $apiMessage = (string) data_get($response->json(), 'error.message', 'Failed to fetch indexing status from Google.');
                if (str_contains(strtolower($apiMessage), 'insufficient')) {
                    $apiMessage .= ' Reconnect Google to grant required Search Console scope.';
                    $this->needsGoogleReconnect = true;
                }
                $this->setGoogleStatusMessage($apiMessage, 'error');

                return;
            }

            $indexStatus = (array) data_get($response->json(), 'inspectionResult.indexStatusResult', []);
            $lastCrawlAt = data_get($indexStatus, 'lastCrawlTime');

            PageIndexingStatus::query()->updateOrCreate(
                [
                    'website_id' => $this->websiteId,
                    'page' => $this->pageUrl,
                ],
                [
                    'last_google_status_checked_at' => now(),
                    'google_verdict' => data_get($indexStatus, 'verdict'),
                    'google_coverage_state' => data_get($indexStatus, 'coverageState'),
                    'google_indexing_state' => data_get($indexStatus, 'indexingState'),
                    'google_last_crawl_at' => is_string($lastCrawlAt) ? Carbon::parse($lastCrawlAt) : null,
                    'google_status_payload' => $indexStatus,
                ]
            );

            $this->setGoogleStatusMessage('Google indexing status refreshed.', 'success');
        } catch (\Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'insufficient')) {
                $this->needsGoogleReconnect = true;
            }
            $this->setGoogleStatusMessage($e->getMessage(), 'error');
        }
    }

    public function render()
    {
        $summary = null;
        $keywords = collect();
        $indexingStatus = null;

        $allowed = ['query', 'total_clicks', 'total_impressions', 'avg_ctr', 'avg_position'];
        $sortBy = in_array($this->sortBy, $allowed) ? $this->sortBy : 'total_clicks';

        if ($this->websiteId && $this->pageUrl && Auth::user()?->canViewWebsiteId($this->websiteId)) {
            $summary = SearchConsoleData::query()
                ->select(
                    DB::raw('SUM(clicks) as total_clicks'),
                    DB::raw('SUM(impressions) as total_impressions'),
                    DB::raw('AVG(position) as avg_position'),
                    DB::raw('AVG(ctr) as avg_ctr'),
                )
                ->where('website_id', $this->websiteId)
                ->where('page', $this->pageUrl)
                ->first();

            $keywords = SearchConsoleData::query()
                ->select(
                    'query',
                    DB::raw('SUM(clicks) as total_clicks'),
                    DB::raw('SUM(impressions) as total_impressions'),
                    DB::raw('AVG(position) as avg_position'),
                    DB::raw('AVG(ctr) as avg_ctr'),
                )
                ->where('website_id', $this->websiteId)
                ->where('page', $this->pageUrl)
                ->groupBy('query')
                ->orderBy($sortBy, $this->sortDir)
                ->paginate(20);

            $indexingStatus = PageIndexingStatus::query()
                ->where('website_id', $this->websiteId)
                ->where('page', $this->pageUrl)
                ->first();
        }

        return view('livewire.pages.page-detail', compact('summary', 'keywords', 'indexingStatus'));
    }

    private function setReindexMessage(string $message, string $kind = 'info'): void
    {
        $this->reindexMessage = $message;
        $this->reindexMessageKind = in_array($kind, ['success', 'info', 'error'], true) ? $kind : 'info';
    }

    private function setGoogleStatusMessage(string $message, string $kind = 'info'): void
    {
        $this->googleStatusMessage = $message;
        $this->googleStatusMessageKind = in_array($kind, ['success', 'info', 'error'], true) ? $kind : 'info';
    }

    private function resolveGoogleAccessToken(GoogleClientFactory $googleClientFactory, $user): string
    {
        /** @var GoogleAccount|null $account */
        $account = $user->googleAccounts()->latest('id')->first();
        if (! $account) {
            throw new \RuntimeException('Connect your Google account first in Settings.');
        }

        $client = $googleClientFactory->make($account);
        $accessToken = (string) ($client->getAccessToken()['access_token'] ?? '');
        if ($accessToken === '') {
            throw new \RuntimeException('Missing Google access token. Please reconnect your Google account.');
        }

        return $accessToken;
    }
}
