<div>
    <form wire:submit.prevent="expand" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Seed keyword</label>
            <input wire:model="seed" type="text" placeholder="best running shoes"
                class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
        </div>
        <div class="w-28">
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Country</label>
            <input wire:model="country" type="text" maxlength="2" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm uppercase shadow-sm dark:border-slate-700 dark:bg-slate-800" />
        </div>
        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
            <span wire:loading.remove>Expand</span>
            <span wire:loading>Working…</span>
        </button>
    </form>

    @if ($status)
        <div class="mb-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">{{ $status }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Query</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Country</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Volume</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Difficulty</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Intent</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                @forelse ($keywords as $kw)
                    <tr>
                        <td class="px-3 py-2">{{ $kw->query }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ strtoupper($kw->country) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $kw->search_volume !== null ? number_format($kw->search_volume) : '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $kw->difficulty_score ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ $kw->intent ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-8 text-center text-xs text-slate-400">No keywords yet — run an expansion to populate.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $keywords->links() }}</div>
</div>
