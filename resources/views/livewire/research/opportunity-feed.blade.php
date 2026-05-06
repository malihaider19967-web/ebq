<div>
    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Keyword</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Page</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Impressions / 30d</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Avg pos</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Opportunity</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                @forelse ($opportunities as $row)
                    <tr>
                        <td class="px-3 py-2">{{ $row['keyword'] }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($row['page'], 60) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['imps']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['pos'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ number_format($row['score'], 1) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-8 text-center text-xs text-slate-400">No opportunities yet — sync GSC and run keyword enrichment to populate.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
