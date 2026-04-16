<div class="space-y-6">
    <div>
        <a href="{{ route('pages.index') }}" class="inline-flex items-center gap-1 text-xs text-slate-500 transition hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
            Back to pages
        </a>
        <h1 class="mt-1.5 truncate text-base font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $pageUrl }}</h1>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" wire:click="requestReindex" wire:loading.attr="disabled" wire:target="requestReindex" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                <span wire:loading.remove wire:target="requestReindex">Request Google reindex</span>
                <span wire:loading wire:target="requestReindex">Requesting…</span>
            </button>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Uses Google Indexing API. URL processing is not guaranteed.</p>
        </div>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            Last indexing request:
            <span class="font-medium text-slate-700 dark:text-slate-300">{{ $lastIndexedAt ? \Illuminate\Support\Carbon::parse($lastIndexedAt)->format('M j, Y g:i A') : 'Never requested' }}</span>
        </p>
        @if ($reindexMessage)
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span @class([
                    'inline-flex rounded-md px-2 py-1 text-xs font-medium',
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $reindexMessageKind === 'success',
                    'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400' => $reindexMessageKind === 'info',
                    'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' => $reindexMessageKind === 'error',
                ])>{{ $reindexMessage }}</span>
                @if ($needsGoogleReconnect)
                    <a href="{{ route('google.redirect') }}" class="inline-flex h-7 items-center rounded-md border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        Reconnect Google
                    </a>
                @endif
            </div>
        @endif
    </div>

    @if ($summary)
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Clicks', 'value' => number_format($summary->total_clicks), 'color' => 'text-blue-600 dark:text-blue-400', 'bg' => 'bg-blue-50 dark:bg-blue-500/10'],
                ['label' => 'Impressions', 'value' => number_format($summary->total_impressions), 'color' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-500/10'],
                ['label' => 'Avg CTR', 'value' => number_format(($summary->avg_ctr ?? 0) * 100, 1).'%', 'color' => 'text-violet-600 dark:text-violet-400', 'bg' => 'bg-violet-50 dark:bg-violet-500/10'],
                ['label' => 'Avg Position', 'value' => number_format($summary->avg_position ?? 0, 1), 'color' => 'text-amber-600 dark:text-amber-400', 'bg' => 'bg-amber-50 dark:bg-amber-500/10'],
            ] as $card)
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-1 text-xl font-bold {{ $card['color'] }}">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div>
        <h3 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">Keywords ranking for this page</h3>

        @if ($keywords instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $keywords->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
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
                                    <td class="whitespace-nowrap px-4 py-2.5 font-medium text-slate-900 dark:text-slate-100">{{ $kw->query }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($kw->total_clicks) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($kw->total_impressions) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format(($kw->avg_ctr ?? 0) * 100, 1) }}%</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                        <span @class([
                                            'inline-flex rounded-full px-1.5 py-px text-[10px] font-semibold tabular-nums',
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
            <div class="mt-3">{{ $keywords->links() }}</div>
        @else
            <div class="flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-16 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No keyword data for this page yet</p>
            </div>
        @endif
    </div>
</div>
