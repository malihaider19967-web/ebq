<div>
    <div class="mb-4 max-w-md">
        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Niche</label>
        <select wire:model.live="nicheId" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Select a niche…</option>
            @foreach ($niches as $niche)
                <option value="{{ $niche->id }}" @selected($nicheId === $niche->id)>{{ $niche->name }}</option>
            @endforeach
        </select>
    </div>

    @if ($nicheId === null)
        <p class="text-xs text-slate-400">Pick a niche to see its top topic clusters.</p>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Topic</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Volume</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Avg difficulty</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Priority</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                    @forelse ($clusters as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row->topic_name }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row->total_search_volume ?? 0) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row->avg_difficulty ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row->priority_score ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-xs text-slate-400">No topic clusters yet for this niche. Topics populate after the weekly cluster refresh.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
