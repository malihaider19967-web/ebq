<?php

namespace App\Livewire\Settings;

use App\Models\Website;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class GscKeywordWindow extends Component
{
    public int $websiteId = 0;

    public ?int $lookbackDays = null;

    public bool $saved = false;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
        $this->loadLookback();
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        $this->saved = false;
        $this->loadLookback();
    }

    public function save(): void
    {
        $this->saved = false;

        $user = Auth::user();
        $website = $this->getWebsite();

        if (! $website || (int) $website->user_id !== (int) $user?->id) {
            return;
        }

        $this->validate([
            'lookbackDays' => ['required', 'integer', 'min:'.(int) config('audit.gsc_keyword_lookback_days_min', 7), 'max:'.(int) config('audit.gsc_keyword_lookback_days_max', 480)],
        ]);

        $website->update(['gsc_keyword_lookback_days' => $this->lookbackDays]);

        $this->saved = true;
    }

    public function render()
    {
        $website = $this->getWebsite();
        $user = Auth::user();
        $isOwner = $website && $user && (int) $website->user_id === (int) $user->id;

        return view('livewire.settings.gsc-keyword-window', [
            'website' => $website,
            'isOwner' => $isOwner,
            'effectiveDays' => $website?->effectiveGscKeywordLookbackDays(),
        ]);
    }

    private function getWebsite(): ?Website
    {
        if (! $this->websiteId) {
            return null;
        }

        $user = Auth::user();

        if (! $user?->canViewWebsiteId($this->websiteId)) {
            return null;
        }

        return Website::find($this->websiteId);
    }

    private function loadLookback(): void
    {
        $website = $this->getWebsite();
        if (! $website) {
            $this->lookbackDays = (int) config('audit.gsc_keyword_lookback_days_default', 28);

            return;
        }

        $this->lookbackDays = $website->gsc_keyword_lookback_days ?? (int) config('audit.gsc_keyword_lookback_days_default', 28);
    }
}
