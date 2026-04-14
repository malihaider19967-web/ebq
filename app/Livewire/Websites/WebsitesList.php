<?php

namespace App\Livewire\Websites;

use App\Models\Website;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WebsitesList extends Component
{
    public string $domain = '';
    public string $gaPropertyId = '';
    public string $gscSiteUrl = '';
    public bool $showForm = false;

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        $this->reset(['domain', 'gaPropertyId', 'gscSiteUrl']);
        $this->resetValidation();
    }

    public function addWebsite(): void
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

        $this->reset(['domain', 'gaPropertyId', 'gscSiteUrl', 'showForm']);
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
}
