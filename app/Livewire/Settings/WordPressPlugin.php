<?php

namespace App\Livewire\Settings;

use App\Models\Website;
use App\Models\WebsiteVerification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class WordPressPlugin extends Component
{
    public int $websiteId = 0;

    public ?string $challengeCode = null;
    public ?string $challengeExpiresAt = null;
    public ?string $plainToken = null;
    public ?string $statusSuccess = null;
    public ?string $statusError = null;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        $this->resetState();
    }

    public function generateChallenge(): void
    {
        $this->resetState();

        $website = $this->authorizedWebsite();
        if (! $website) {
            return;
        }

        $verification = WebsiteVerification::issueFor($website);
        $this->challengeCode = $verification->challenge_code;
        $this->challengeExpiresAt = $verification->expires_at->toDateTimeString();
    }

    public function verify(): void
    {
        $this->statusSuccess = null;
        $this->statusError = null;

        $website = $this->authorizedWebsite();
        if (! $website) {
            return;
        }

        $verification = WebsiteVerification::query()
            ->where('website_id', $website->id)
            ->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (! $verification) {
            $this->statusError = 'No active challenge — generate a new code first.';

            return;
        }

        $verification->forceFill(['last_attempt_at' => Carbon::now()])->save();
        $url = rtrim('https://'.$website->domain, '/').'/.well-known/ebq-verification.txt';

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'EBQ-Verifier/1.0'])
                ->get($url);
        } catch (Throwable $e) {
            $this->statusError = 'Could not fetch '.$url.' — '.$e->getMessage();

            return;
        }

        if (! $response->successful()) {
            $this->statusError = 'Got HTTP '.$response->status().' fetching '.$url.'. Make sure the plugin is active on this domain.';

            return;
        }

        if (trim((string) $response->body()) !== $verification->challenge_code) {
            $this->statusError = 'The file is served but its contents don\'t match the expected code.';

            return;
        }

        $verification->forceFill(['verified_at' => Carbon::now()])->save();

        $this->plainToken = $website->createToken('WordPress plugin', ['read:insights'])->plainTextToken;
        $this->statusSuccess = 'Verified. Paste the token below into your plugin settings — it\'s shown only once.';
        $this->challengeCode = null;
    }

    public function revokeToken(int $tokenId): void
    {
        $website = $this->authorizedWebsite();
        if (! $website) {
            return;
        }

        PersonalAccessToken::query()
            ->where('tokenable_type', Website::class)
            ->where('tokenable_id', $website->id)
            ->whereKey($tokenId)
            ->delete();

        $this->statusSuccess = 'Token revoked.';
    }

    public function render()
    {
        $website = $this->authorizedWebsite();
        $tokens = [];
        if ($website) {
            $tokens = PersonalAccessToken::query()
                ->where('tokenable_type', Website::class)
                ->where('tokenable_id', $website->id)
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'last_used_at', 'created_at', 'abilities']);
        }

        return view('livewire.settings.wordpress-plugin', [
            'website' => $website,
            'tokens' => $tokens,
        ]);
    }

    private function authorizedWebsite(): ?Website
    {
        if ($this->websiteId <= 0) {
            $this->statusError = 'Select a website first.';

            return null;
        }

        $user = Auth::user();
        if (! $user || ! $user->canViewWebsiteId($this->websiteId)) {
            $this->statusError = 'You do not have access to this website.';

            return null;
        }

        return Website::find($this->websiteId);
    }

    private function resetState(): void
    {
        $this->challengeCode = null;
        $this->challengeExpiresAt = null;
        $this->plainToken = null;
        $this->statusSuccess = null;
        $this->statusError = null;
    }
}
