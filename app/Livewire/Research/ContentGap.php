<?php

namespace App\Livewire\Research;

use App\Models\Website;
use App\Services\Research\Intelligence\ContentGapEngine;
use Livewire\Attributes\Url;
use Livewire\Component;

class ContentGap extends Component
{
    #[Url(as: 'competitors')]
    public string $competitors = '';

    public int $websiteId = 0;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    public function render(ContentGapEngine $engine)
    {
        $missing = collect();
        if ($this->websiteId > 0 && trim($this->competitors) !== '') {
            $website = Website::query()->find($this->websiteId);
            $domains = array_filter(array_map('trim', preg_split('/[\s,]+/', $this->competitors) ?: []));
            if ($website && $domains !== []) {
                $missing = $engine->missingKeywords($website, $domains);
            }
        }

        return view('livewire.research.content-gap', compact('missing'));
    }
}
