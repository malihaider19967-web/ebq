<div>
    <div class="mb-4">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search pages..."
            class="w-full max-w-xs rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
    </div>

    @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->isNotEmpty())
        <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        <th class="px-4 py-3">Page</th>
                        <th class="px-4 py-3 text-right">Clicks</th>
                        <th class="px-4 py-3 text-right">Impressions</th>
                        <th class="px-4 py-3 text-right">Avg CTR</th>
                        <th class="px-4 py-3 text-right">Avg Position</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-slate-100 dark:border-slate-800">
                            <td class="max-w-xs truncate px-4 py-3 font-medium">
                                <a href="{{ route('pages.show', ['id' => urlencode($row->page)]) }}" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ $row->page }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right">{{ number_format($row->total_clicks) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row->total_impressions) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row->avg_ctr * 100, 1) }}%</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row->avg_position, 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    @else
        <div class="rounded-lg bg-white p-8 text-center text-sm text-slate-400 shadow dark:bg-slate-900">
            No page data yet. Data appears after the daily sync runs.
        </div>
    @endif
</div>
