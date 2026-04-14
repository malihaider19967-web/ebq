<?php

namespace App\Livewire\Onboarding;

use App\Models\Website;
use App\Services\Google\GoogleAnalyticsService;
use App\Services\Google\SearchConsoleService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ConnectGoogle extends Component
{
    public int $step = 1;

    public string $gaPropertyId = '';
    public string $gscSiteUrl = '';
    public string $domain = '';

    public bool $googleConnected = false;
    public bool $loading = false;
    public string $fetchError = '';

    /** @var array<int, array{id: string, name: string}> */
    public array $gaProperties = [];

    /** @var array<int, array{siteUrl: string, permissionLevel: string}> */
    public array $gscSites = [];

    public function mount(): void
    {
        $user = Auth::user();
        $this->googleConnected = (bool) $user?->googleAccounts()->exists();

        if ($this->googleConnected) {
            $this->step = 2;
            $this->fetchGoogleData();
        }
    }

    public function goToStep(int $step): void
    {
        if ($step === 2 && ! $this->googleConnected) {
            return;
        }

        $this->step = $step;
    }

    public function updatedGscSiteUrl(string $value): void
    {
        if ($value && $this->domain === '') {
            $this->domain = $this->extractDomain($value);
        }
    }

    public function saveWebsite(): void
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

        $this->redirectRoute('dashboard');
    }

    public function render()
    {
        return view('livewire.onboarding.connect-google');
    }

    private function fetchGoogleData(): void
    {
        $account = Auth::user()?->googleAccounts()->latest()->first();

        if (! $account) {
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
