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

        /** @var GoogleAccount|null $account */
        $account = $user->googleAccounts()->latest('id')->first();
        if (! $account) {
            $this->setReindexMessage('Connect your Google account first in Settings.', 'error');

            return;
        }

        try {
            $client = $googleClientFactory->make($account);
            $accessToken = (string) ($client->getAccessToken()['access_token'] ?? '');
            if ($accessToken === '') {
                throw new \RuntimeException('Missing Google access token. Please reconnect your Google account.');
            }

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
                        'last_indexed_at' => $parsedIndexedAt,
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

    public function render()
    {
        $summary = null;
        $keywords = collect();
        $lastIndexedAt = null;

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

            $lastIndexedAt = PageIndexingStatus::query()
                ->where('website_id', $this->websiteId)
                ->where('page', $this->pageUrl)
                ->value('last_indexed_at');
        }

        return view('livewire.pages.page-detail', compact('summary', 'keywords', 'lastIndexedAt'));
    }

    private function setReindexMessage(string $message, string $kind = 'info'): void
    {
        $this->reindexMessage = $message;
        $this->reindexMessageKind = in_array($kind, ['success', 'info', 'error'], true) ? $kind : 'info';
    }
}
