<?php

namespace App\Livewire\Research;

use App\Models\Research\ContentBrief;
use App\Models\Research\Keyword;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ContentBriefGenerator extends Component
{
    public int $websiteId = 0;
    public string $seedKeyword = '';
    public string $status = '';

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
    }

    public function create(): void
    {
        $this->validate(['seedKeyword' => 'required|string|min:2|max:200']);

        if ($this->websiteId === 0) {
            $this->status = 'Pick a website first.';

            return;
        }

        $keyword = Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor($this->seedKeyword), 'country' => 'us', 'language' => 'en'],
            ['query' => $this->seedKeyword, 'normalized_query' => Keyword::normalize($this->seedKeyword)]
        );

        ContentBrief::create([
            'website_id' => $this->websiteId,
            'keyword_id' => $keyword->id,
            'created_by' => Auth::id(),
            'payload' => [
                'status' => 'queued',
                'created_via' => 'research_section',
                'note' => 'Brief generation runs in Phase-4 ContentBriefService.',
            ],
        ]);

        $this->seedKeyword = '';
        $this->status = 'Brief queued.';
    }

    public function render()
    {
        $briefs = $this->websiteId === 0
            ? collect()
            : ContentBrief::query()
                ->with('keyword:id,query')
                ->where('website_id', $this->websiteId)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

        return view('livewire.research.content-brief-generator', compact('briefs'));
    }
}
