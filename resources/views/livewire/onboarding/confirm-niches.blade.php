<div>
    @if ($visible)
        <div class="mt-8 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Confirm your niches</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">We've inferred these from your Search Console queries. Adjust the weights or remove any that don't fit.</p>

            @if ($status)
                <div class="mt-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-800/60">{{ $status }}</div>
            @endif

            <form wire:submit.prevent="save" class="mt-4 space-y-2">
                @foreach ($assignments as $i => $row)
                    <div class="flex items-center gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-950">
                        <input type="checkbox" wire:model="assignments.{{ $i }}.keep" class="rounded border-slate-300">
                        <div class="flex-1">
                            <div class="text-sm font-medium">{{ $row['name'] }}</div>
                            <input type="range" min="0" max="1" step="0.01" wire:model="assignments.{{ $i }}.weight" class="mt-1 w-full" />
                        </div>
                        <div class="w-16 text-right text-xs tabular-nums text-slate-500">{{ number_format(($row['weight'] ?? 0) * 100, 0) }}%</div>
                    </div>
                @endforeach
                <button type="submit" class="mt-2 inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">Save niches</button>
            </form>
        </div>
    @endif
</div>
