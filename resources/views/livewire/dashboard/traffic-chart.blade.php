<div class="rounded-lg bg-white p-5 shadow dark:bg-slate-900">
    <h3 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Traffic &mdash; Last 30 Days</h3>

    @if ($days->isEmpty())
        <p class="py-8 text-center text-sm text-slate-400">No data yet. Sync will run automatically or you can trigger it from the command line.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Clicks</th>
                        <th class="py-2">Users</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $day)
                        <tr class="border-b border-slate-100 dark:border-slate-800">
                            <td class="py-2 pr-4 text-slate-600 dark:text-slate-300">{{ $day['date'] }}</td>
                            <td class="py-2 pr-4 font-medium">{{ number_format($day['clicks']) }}</td>
                            <td class="py-2 font-medium">{{ number_format($day['users']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
