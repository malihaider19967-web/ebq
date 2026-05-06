<?php

namespace App\Livewire\Research;

use App\Models\Research\Niche;
use App\Models\Research\NicheTopicCluster;
use Livewire\Attributes\Url;
use Livewire\Component;

class TopicExplorer extends Component
{
    #[Url(as: 'niche')]
    public ?int $nicheId = null;

    public function render()
    {
        $niches = Niche::query()
            ->where('is_approved', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'parent_id']);

        $clusters = $this->nicheId !== null
            ? NicheTopicCluster::query()
                ->where('niche_id', $this->nicheId)
                ->with('cluster:id,cluster_name')
                ->orderByDesc('priority_score')
                ->limit(50)
                ->get()
            : collect();

        return view('livewire.research.topic-explorer', compact('niches', 'clusters'));
    }
}
