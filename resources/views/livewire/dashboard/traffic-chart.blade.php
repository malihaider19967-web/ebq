<div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Traffic Overview</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Last 30 days</p>
        </div>
        <div class="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span> Clicks</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Users</span>
        </div>
    </div>

    @if ($days->isEmpty())
        <div class="flex flex-col items-center justify-center px-6 py-16">
            <svg class="h-12 w-12 text-slate-300 dark:text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" /></svg>
            <p class="mt-3 text-sm font-medium text-slate-500 dark:text-slate-400">No traffic data yet</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Data will appear after the daily sync runs.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3 text-right">Clicks</th>
                        <th class="px-6 py-3 text-right">Users</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($days as $day)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="whitespace-nowrap px-6 py-3 text-slate-600 dark:text-slate-300">{{ $day['date'] }}</td>
                            <td class="whitespace-nowrap px-6 py-3 text-right font-medium text-slate-900 dark:text-slate-100">{{ number_format($day['clicks']) }}</td>
                            <td class="whitespace-nowrap px-6 py-3 text-right font-medium text-slate-900 dark:text-slate-100">{{ number_format($day['users']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
