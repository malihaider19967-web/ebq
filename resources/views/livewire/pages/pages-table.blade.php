<div>
    <div class="mb-4">
        <div class="relative sm:max-w-xs">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search pages..."
                class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm placeholder-slate-400 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:placeholder-slate-500" />
        </div>
    </div>

    @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                            <x-sort-header column="page" :sortBy="$sortBy" :sortDir="$sortDir">Page</x-sort-header>
                            <x-sort-header column="total_clicks" :sortBy="$sortBy" :sortDir="$sortDir" align="right">Clicks</x-sort-header>
                            <x-sort-header column="total_impressions" :sortBy="$sortBy" :sortDir="$sortDir" align="right">Impressions</x-sort-header>
                            <x-sort-header column="avg_ctr" :sortBy="$sortBy" :sortDir="$sortDir" align="right">Avg CTR</x-sort-header>
                            <x-sort-header column="avg_position" :sortBy="$sortBy" :sortDir="$sortDir" align="right">Avg Position</x-sort-header>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($rows as $row)
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="max-w-sm truncate px-6 py-3.5">
                                    <a href="{{ route('pages.show', ['id' => urlencode($row->page)]) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        {{ $row->page }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($row->total_clicks) }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($row->total_impressions) }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($row->avg_ctr * 100, 1) }}%</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-right">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $row->avg_position <= 3,
                                        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' => $row->avg_position > 3 && $row->avg_position <= 10,
                                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' => $row->avg_position > 10 && $row->avg_position <= 20,
                                        'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => $row->avg_position > 20,
                                    ])>{{ number_format($row->avg_position, 1) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    @else
        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-16 dark:border-slate-800 dark:bg-slate-900">
            <svg class="h-12 w-12 text-slate-300 dark:text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
            <p class="mt-3 text-sm font-medium text-slate-500 dark:text-slate-400">No page data yet</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Data will appear after the daily sync runs.</p>
        </div>
    @endif
</div>
