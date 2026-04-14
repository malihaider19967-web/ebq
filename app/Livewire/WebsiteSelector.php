<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WebsiteSelector extends Component
{
    public int $websiteId = 0;

    /** @var array<int, array{id: int, domain: string}> */
    public array $websites = [];

    public function mount(): void
    {
        $this->websites = Auth::user()
            ->websites()
            ->select('id', 'domain')
            ->get()
            ->map(fn ($w) => ['id' => $w->id, 'domain' => $w->domain])
            ->toArray();

        $sessionId = (int) session('current_website_id', 0);
        $ids = array_column($this->websites, 'id');

        $this->websiteId = in_array($sessionId, $ids) ? $sessionId : ($ids[0] ?? 0);

        if ($this->websiteId) {
            session(['current_website_id' => $this->websiteId]);
        }
    }

    public function updatedWebsiteId(int $value): void
    {
        session(['current_website_id' => $value]);
        $this->dispatch('website-changed', websiteId: $value);
    }

    public function render()
    {
        return view('livewire.website-selector');
    }
}
