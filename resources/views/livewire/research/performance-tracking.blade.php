<div>
    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Keyword</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Page</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Avg pos</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Impressions / 90d</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">CTR</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Niche benchmark</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                @forelse ($rows as $row)
                    <tr @class(['bg-amber-50/50 dark:bg-amber-900/10' => $row['underperforming']])>
                        <td class="px-3 py-2">{{ $row['keyword'] }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($row['page'], 60) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['avg_position'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['impressions']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['ctr'] * 100, 2) }}%</td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            @if ($row['benchmark'] !== null)
                                {{ number_format($row['benchmark'] * 100, 2) }}%
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            @if ($row['underperforming'])
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Below niche</span>
                            @elseif ($row['benchmark'] !== null)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">On par</span>
                            @else
                                <span class="text-[10px] text-slate-400">no benchmark</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-8 text-center text-xs text-slate-400">No GSC + niche data yet — link niches to keywords (Onboarding) and let GSC sync run.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
