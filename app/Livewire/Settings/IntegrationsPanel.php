<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class IntegrationsPanel extends Component
{
    public function render()
    {
        $googleAccount = Auth::user()->googleAccounts()->latest()->first();

        return view('livewire.settings.integrations-panel', compact('googleAccount'));
    }
}
