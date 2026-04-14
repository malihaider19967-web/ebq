<div>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search keywords..."
            class="w-full max-w-xs rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
        <select wire:model.live="device"
            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800">
            <option value="">All devices</option>
            <option value="DESKTOP">Desktop</option>
            <option value="MOBILE">Mobile</option>
            <option value="TABLET">Tablet</option>
        </select>
        <input wire:model.live="from" type="date" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800" />
        <input wire:model.live="to" type="date" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800" />
    </div>

    @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->isNotEmpty())
        <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        <th class="px-4 py-3">Keyword</th>
                        <th class="px-4 py-3 text-right">Clicks</th>
                        <th class="px-4 py-3 text-right">Impressions</th>
                        <th class="px-4 py-3 text-right">CTR</th>
                        <th class="px-4 py-3 text-right">Position</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-slate-100 dark:border-slate-800">
                            <td class="px-4 py-3 font-medium">{{ $row->query }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row->clicks) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row->impressions) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row->ctr * 100, 1) }}%</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row->position, 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    @else
        <div class="rounded-lg bg-white p-8 text-center text-sm text-slate-400 shadow dark:bg-slate-900">
            No keyword data yet. Data appears after the daily sync runs.
        </div>
    @endif
</div>
