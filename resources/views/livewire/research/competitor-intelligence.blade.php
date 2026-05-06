<div>
    <div class="mb-4 max-w-md">
        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Competitor domain</label>
        <input wire:model.live.debounce.500ms="domain" type="text" placeholder="competitor.com"
            class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
    </div>

    @if ($domain === '')
        <p class="text-xs text-slate-400">Enter a competitor domain — we'll list keywords where they show up in our crawled SERPs.</p>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Keyword</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Volume</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Difficulty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                    @forelse ($keywords as $kw)
                        <tr>
                            <td class="px-3 py-2">{{ $kw->query }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $kw->search_volume !== null ? number_format($kw->search_volume) : '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $kw->difficulty_score ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-8 text-center text-xs text-slate-400">No SERP data yet for this domain. Crawl more keywords (Keyword Research) to populate.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
