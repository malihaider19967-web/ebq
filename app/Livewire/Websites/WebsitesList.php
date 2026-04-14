<?php

namespace App\Livewire\Websites;

use App\Models\Website;
use App\Services\Google\GoogleAnalyticsService;
use App\Services\Google\SearchConsoleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class WebsitesList extends Component
{
    public string $domain = '';
    public string $gaPropertyId = '';
    public string $gscSiteUrl = '';
    public bool $showForm = false;
    public string $fetchError = '';

    /** @var array<int, array{id: string, name: string}> */
    public array $gaProperties = [];

    /** @var array<int, array{siteUrl: string, permissionLevel: string}> */
    public array $gscSites = [];

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        $this->reset(['domain', 'gaPropertyId', 'gscSiteUrl', 'fetchError']);
        $this->resetValidation();

        if ($this->showForm) {
            $this->fetchGoogleData();
        }
    }

    public function updatedGscSiteUrl(string $value): void
    {
        if ($value && $this->domain === '') {
            $this->domain = $this->extractDomain($value);
        }
    }

    public function addWebsite(): void
    {
        $this->validate([
            'domain' => ['required', 'string', 'max:255'],
            'gaPropertyId' => ['required', 'string', 'max:255'],
            'gscSiteUrl' => ['required', 'string', 'max:255'],
        ]);

        $website = Website::updateOrCreate(
            ['user_id' => Auth::id(), 'domain' => $this->domain],
            ['ga_property_id' => $this->gaPropertyId, 'gsc_site_url' => $this->gscSiteUrl]
        );

        if ($website->wasRecentlyCreated) {
            Artisan::queue('growthhub:import-historical', [
                '--days' => 365,
                '--website' => (string) $website->id,
            ]);
        }

        $this->reset(['domain', 'gaPropertyId', 'gscSiteUrl', 'showForm', 'fetchError']);
    }

    public function removeWebsite(int $id): void
    {
        Website::where('id', $id)->where('user_id', Auth::id())->delete();

        if ((int) session('current_website_id') === $id) {
            $next = Auth::user()->websites()->first();
            session(['current_website_id' => $next?->id ?? 0]);
        }
    }

    public function render()
    {
        $websites = Auth::user()->websites()->get();

        return view('livewire.websites.websites-list', compact('websites'));
    }

    private function fetchGoogleData(): void
    {
        $account = Auth::user()?->googleAccounts()->latest()->first();

        if (! $account) {
            $this->fetchError = 'No Google account connected. Connect one in Settings first.';
            return;
        }

        try {
            $this->gaProperties = app(GoogleAnalyticsService::class)->listProperties($account);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch GA properties: '.$e->getMessage());
            $this->fetchError = 'Could not load GA4 properties. You can type the ID manually.';
        }

        try {
            $this->gscSites = app(SearchConsoleService::class)->listSites($account);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch GSC sites: '.$e->getMessage());
            $this->fetchError = 'Could not load Search Console sites. You can type the URL manually.';
        }
    }

    private function extractDomain(string $siteUrl): string
    {
        $url = str_replace('sc-domain:', '', $siteUrl);
        $parsed = parse_url($url, PHP_URL_HOST);

        return $parsed ?: preg_replace('#^https?://#', '', rtrim($url, '/'));
    }
}
