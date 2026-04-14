<?php

namespace App\Livewire\Onboarding;

use App\Models\Website;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ConnectGoogle extends Component
{
    public string $domain = '';
    public string $gaPropertyId = '';
    public string $gscSiteUrl = '';

    public function saveSelection(): void
    {
        $this->validate([
            'domain' => ['required', 'string'],
            'gaPropertyId' => ['required', 'string'],
            'gscSiteUrl' => ['required', 'string'],
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
