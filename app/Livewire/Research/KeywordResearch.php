<?php

namespace App\Livewire\Research;

use App\Models\Research\Keyword;
use App\Models\Website;
use App\Services\Research\KeywordExpansionService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class KeywordResearch extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $seed = '';

    #[Url(as: 'country')]
    public string $country = 'us';

    public int $websiteId = 0;
    public string $status = '';

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    public function expand(KeywordExpansionService $service): void
    {
        $this->validate([
            'seed' => 'required|string|min:2|max:200',
            'country' => 'required|string|min:2|max:8',
        ]);

        $website = $this->websiteId > 0 ? Website::query()->find($this->websiteId) : null;
        try {
            $found = $service->expand($this->seed, $this->country, $website);
            $this->status = sprintf('Expanded into %d keyword(s).', $found->count());
        } catch (\Throwable $e) {
            $this->status = 'Expansion failed: '.$e->getMessage();
        }
    }

    public function render()
    {
        $keywords = Keyword::query()
            ->leftJoin('keyword_intelligence', 'keyword_intelligence.keyword_id', '=', 'keywords.id')
            ->when($this->seed, fn ($q) => $q->where('keywords.normalized_query', 'like', '%'.Keyword::normalize($this->seed).'%'))
            ->when($this->country !== '', fn ($q) => $q->where('keywords.country', $this->country))
            ->orderByDesc('keyword_intelligence.search_volume')
            ->orderByDesc('keywords.id')
            ->select(['keywords.*', 'keyword_intelligence.search_volume', 'keyword_intelligence.difficulty_score', 'keyword_intelligence.intent'])
            ->paginate(25);

        return view('livewire.research.keyword-research', compact('keywords'));
    }
}
