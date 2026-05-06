<div>
    <div class="mb-4">
        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Competitor domains (comma- or space-separated)</label>
        <input wire:model.live.debounce.700ms="competitors" type="text" placeholder="competitor.com other.com"
            class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
    </div>

    @if ($missing->isEmpty())
        <p class="text-xs text-slate-400">Enter at least one competitor — we'll show keywords they rank for that you don't.</p>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Missing keyword</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Country</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                    @foreach ($missing as $kw)
                        <tr>
                            <td class="px-3 py-2">{{ $kw->query }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ strtoupper($kw->country) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
