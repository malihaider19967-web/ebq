<div class="space-y-6">
    <div>
        <a href="{{ route('pages.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 transition hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
            Back to pages
        </a>
        <h1 class="mt-2 truncate text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $pageUrl }}</h1>
    </div>

    @if ($summary)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Clicks', 'value' => number_format($summary->total_clicks), 'color' => 'text-blue-600 dark:text-blue-400', 'bg' => 'bg-blue-50 dark:bg-blue-500/10'],
                ['label' => 'Impressions', 'value' => number_format($summary->total_impressions), 'color' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-500/10'],
                ['label' => 'Avg CTR', 'value' => number_format(($summary->avg_ctr ?? 0) * 100, 1).'%', 'color' => 'text-violet-600 dark:text-violet-400', 'bg' => 'bg-violet-50 dark:bg-violet-500/10'],
                ['label' => 'Avg Position', 'value' => number_format($summary->avg_position ?? 0, 1), 'color' => 'text-amber-600 dark:text-amber-400', 'bg' => 'bg-amber-50 dark:bg-amber-500/10'],
            ] as $card)
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-2 text-2xl font-bold {{ $card['color'] }}">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div>
        <h3 class="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">Keywords ranking for this page</h3>

        @if ($keywords instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $keywords->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                                <x-sort-header column="query" :sortBy="$sortBy" :sortDir="$sortDir">Keyword</x-sort-header>
                                <x-sort-header column="total_clicks" :sortBy="$sortBy" :sortDir="$sortDir" align="right">Clicks</x-sort-header>
                                <x-sort-header column="total_impressions" :sortBy="$sortBy" :sortDir="$sortDir" align="right">Impressions</x-sort-header>
                                <x-sort-header column="avg_ctr" :sortBy="$sortBy" :sortDir="$sortDir" align="right">Avg CTR</x-sort-header>
                                <x-sort-header column="avg_position" :sortBy="$sortBy" :sortDir="$sortDir" align="right">Avg Position</x-sort-header>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($keywords as $kw)
                                <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="whitespace-nowrap px-6 py-3.5 font-medium text-slate-900 dark:text-slate-100">{{ $kw->query }}</td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($kw->total_clicks) }}</td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($kw->total_impressions) }}</td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format(($kw->avg_ctr ?? 0) * 100, 1) }}%</td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-right">
                                        <span @class([
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums',
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => ($kw->avg_position ?? 0) <= 3,
                                            'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' => ($kw->avg_position ?? 0) > 3 && ($kw->avg_position ?? 0) <= 10,
                                            'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' => ($kw->avg_position ?? 0) > 10 && ($kw->avg_position ?? 0) <= 20,
                                            'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => ($kw->avg_position ?? 0) > 20,
                                        ])>{{ number_format($kw->avg_position ?? 0, 1) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $keywords->links() }}</div>
        @else
            <div class="flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-16 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No keyword data for this page yet</p>
            </div>
        @endif
    </div>
</div>
