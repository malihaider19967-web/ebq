<?php

namespace App\Livewire\Onboarding;

use App\Models\Website;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ConnectGoogle extends Component
{
    public int $step = 1;

    public string $domain = '';
    public string $gaPropertyId = '';
    public string $gscSiteUrl = '';

    public bool $googleConnected = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->googleConnected = (bool) $user?->googleAccounts()->exists();

        if ($this->googleConnected) {
            $this->step = 2;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step === 2 && ! $this->googleConnected) {
            return;
        }

        $this->step = $step;
    }

    public function saveWebsite(): void
    {
        $this->validate([
            'domain' => ['required', 'string', 'max:255'],
            'gaPropertyId' => ['required', 'string', 'max:255'],
            'gscSiteUrl' => ['required', 'string', 'max:255'],
        ]);

        Website::updateOrCreate(
            ['user_id' => Auth::id(), 'domain' => $this->domain],
            ['ga_property_id' => $this->gaPropertyId, 'gsc_site_url' => $this->gscSiteUrl]
        );

        $this->redirectRoute('dashboard');
    }

    public function render()
    {
        return view('livewire.onboarding.connect-google');
    }
}
