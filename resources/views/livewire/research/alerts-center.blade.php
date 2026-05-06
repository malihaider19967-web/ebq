<div>
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <select wire:model.live="type" class="h-8 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">All types</option>
            <option value="ranking_drop">Ranking drop</option>
            <option value="new_opportunity">New opportunity</option>
            <option value="serp_change">SERP change</option>
            <option value="volatility_spike">Volatility spike</option>
        </select>
        <label class="flex items-center gap-2 text-xs">
            <input type="checkbox" wire:model.live="showAcknowledged" class="rounded border-slate-300">
            Show acknowledged
        </label>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Type</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Keyword</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Severity</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">When</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                @forelse ($alerts as $alert)
                    <tr>
                        <td class="px-3 py-2 text-xs font-semibold uppercase">{{ str_replace('_', ' ', $alert->type) }}</td>
                        <td class="px-3 py-2">{{ $alert->keyword?->query ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs">{{ $alert->severity }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ $alert->created_at?->diffForHumans() }}</td>
                        <td class="px-3 py-2 text-right">
                            @if ($alert->acknowledged_at === null)
                                <button wire:click="acknowledge({{ $alert->id }})" class="rounded-md border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">Ack</button>
                            @else
                                <span class="text-[10px] text-slate-400">acked</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-8 text-center text-xs text-slate-400">No alerts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $alerts->links() }}</div>
</div>
