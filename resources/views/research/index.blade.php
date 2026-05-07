<x-layouts.app>
    @php
        $cards = [
            ['route' => 'research.keywords', 'label' => 'Keyword Research', 'desc' => 'Expand a seed keyword into PAA, related, autocomplete and enrich with KE volume + LLM intent.'],
            ['route' => 'research.topics', 'label' => 'Topic Explorer', 'desc' => 'Browse niche topic clusters with demand, difficulty and priority scores.'],
            ['route' => 'research.serp', 'label' => 'SERP Analysis', 'desc' => 'Latest SERP snapshot for a keyword with weakness flags and SERP features.'],
            ['route' => 'research.competitors', 'label' => 'Competitor Intelligence', 'desc' => 'A domain\'s ranking keywords across the SERPs we\'ve crawled.'],
            ['route' => 'research.gap', 'label' => 'Content Gap', 'desc' => 'Keywords competitors rank for and you don\'t.'],
            ['route' => 'research.opportunities', 'label' => 'Opportunities', 'desc' => 'Prioritised page-keyword pairs ranked by improvement potential.'],
            ['route' => 'research.reverse', 'label' => 'Reverse Research', 'desc' => 'Pick a URL — see its keyword universe and estimated traffic.'],
            ['route' => 'research.briefs', 'label' => 'Content Briefs', 'desc' => 'AI-assisted briefs grounded in the SERP and your niche aggregate.'],
            ['route' => 'research.authority', 'label' => 'Topical Authority', 'desc' => 'Per-niche topic-cluster tree with coverage colouring.'],
            ['route' => 'research.coverage', 'label' => 'Coverage Score', 'desc' => 'Per-page subtopic coverage benchmarked against the niche.'],
            ['route' => 'research.internal-links', 'label' => 'Internal Links', 'desc' => 'Suggested source pages for any target page\'s primary keywords.'],
            ['route' => 'research.alerts', 'label' => 'Alerts', 'desc' => 'Ranking drops, new opportunities, SERP changes, volatility spikes.'],
            ['route' => 'research.performance', 'label' => 'Performance vs niche', 'desc' => 'Page-keyword CTR benchmarked against the anonymised niche aggregate.'],
        ];
    @endphp
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Research</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Keyword, topic, SERP, competitor and content research backed by your GSC + niche aggregates.</p>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($cards as $card)
                <a href="{{ route($card['route']) }}" class="block rounded-lg border border-slate-200 bg-white p-4 transition hover:border-indigo-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700">
                    <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $card['label'] }}</div>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $card['desc'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.app>
