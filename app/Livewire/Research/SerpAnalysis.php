<?php

namespace App\Livewire\Research;

use App\Models\Research\Keyword;
use App\Models\Research\SerpFeature;
use App\Models\Research\SerpResult;
use App\Models\Research\SerpSnapshot;
use App\Models\Website;
use App\Services\Research\Intelligence\SerpWeaknessEngine;
use App\Services\Research\SerpIngestionService;
use Livewire\Attributes\Url;
use Livewire\Component;

class SerpAnalysis extends Component
{
    #[Url(as: 'q')]
    public string $query = '';

    #[Url(as: 'country')]
    public string $country = 'us';

    public string $status = '';

    public function fetch(SerpIngestionService $serp, SerpWeaknessEngine $weakness): void
    {
        $this->validate([
            'query' => 'required|string|min:2|max:200',
            'country' => 'required|string|min:2|max:8',
        ]);

        $keyword = Keyword::firstOrCreate(
            ['query_hash' => Keyword::hashFor($this->query), 'country' => $this->country, 'language' => 'en'],
            ['query' => $this->query, 'normalized_query' => Keyword::normalize($this->query)]
        );

        $website = ($id = (int) session('current_website_id', 0)) ? Website::query()->find($id) : null;
        $snapshot = $serp->ingest($keyword, website: $website);

        if ($snapshot !== null) {
            $weakness->scan($snapshot);
            $this->status = 'Snapshot fetched.';
        } else {
            $this->status = 'Could not fetch SERP — check Serper config.';
        }
    }

    public function render()
    {
        $snapshot = null;
        $results = collect();
        $features = collect();

        if ($this->query !== '') {
            $keyword = Keyword::query()
                ->where('query_hash', Keyword::hashFor($this->query))
                ->where('country', $this->country)
                ->first();

            if ($keyword !== null) {
                $snapshot = SerpSnapshot::query()->where('keyword_id', $keyword->id)->orderByDesc('fetched_at')->first();
                if ($snapshot !== null) {
                    $results = SerpResult::query()->where('snapshot_id', $snapshot->id)->orderBy('rank')->limit(10)->get();
                    $features = SerpFeature::query()->where('snapshot_id', $snapshot->id)->get();
                }
            }
        }

        return view('livewire.research.serp-analysis', compact('snapshot', 'results', 'features'));
    }
}
