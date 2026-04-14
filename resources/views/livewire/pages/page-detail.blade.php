<div class="space-y-5">
    <div>
        <a href="{{ route('pages.index') }}" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">&larr; All pages</a>
        <h2 class="mt-2 truncate text-lg font-semibold">{{ $pageUrl }}</h2>
    </div>

    @if ($summary)
        <div class="grid gap-4 sm:grid-cols-4">
            @foreach ([
                ['label' => 'Clicks', 'value' => number_format($summary->total_clicks)],
                ['label' => 'Impressions', 'value' => number_format($summary->total_impressions)],
                ['label' => 'Avg CTR', 'value' => number_format(($summary->avg_ctr ?? 0) * 100, 1).'%'],
                ['label' => 'Avg Position', 'value' => number_format($summary->avg_position ?? 0, 1)],
            ] as $card)
                <div class="rounded-lg bg-white p-4 shadow dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-1 text-xl font-bold">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div>
        <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Keywords ranking for this page</h3>

        @if ($keywords instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $keywords->isNotEmpty())
            <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <th class="px-4 py-3">Keyword</th>
                            <th class="px-4 py-3 text-right">Clicks</th>
                            <th class="px-4 py-3 text-right">Impressions</th>
                            <th class="px-4 py-3 text-right">Avg CTR</th>
                            <th class="px-4 py-3 text-right">Avg Position</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keywords as $kw)
                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                <td class="px-4 py-3 font-medium">{{ $kw->query }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($kw->total_clicks) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($kw->total_impressions) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format(($kw->avg_ctr ?? 0) * 100, 1) }}%</td>
                                <td class="px-4 py-3 text-right">{{ number_format($kw->avg_position ?? 0, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $keywords->links() }}</div>
        @else
            <div class="rounded-lg bg-white p-8 text-center text-sm text-slate-400 shadow dark:bg-slate-900">
                No keyword data for this page yet.
            </div>
        @endif
    </div>
</div>
