<div>
    <div class="mb-4 max-w-md">
        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Niche</label>
        <select wire:model.live="nicheId" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Select…</option>
            @foreach ($niches as $niche)
                <option value="{{ $niche->id }}" @selected($nicheId === $niche->id)>{{ $niche->name }}</option>
            @endforeach
        </select>
    </div>

    @if ($nicheId === null)
        <p class="text-xs text-slate-400">Pick a niche to see its topic clusters and your coverage.</p>
    @else
        <div class="space-y-2">
            @forelse ($rows as $row)
                @php
                    $clusterKeywordIds = $row->cluster?->keywords->pluck('id') ?? collect();
                    $covered = $clusterKeywordIds->intersect($coveredKeywordIds)->count();
                    $total = $clusterKeywordIds->count();
                    $coverage = $total > 0 ? $covered / $total : 0;
                    $bg = $coverage >= 0.66 ? 'bg-emerald-500' : ($coverage >= 0.33 ? 'bg-amber-500' : 'bg-rose-500');
                @endphp
                <div class="rounded-md border border-slate-200 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-950">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium">{{ $row->topic_name }}</div>
                        <div class="text-xs text-slate-500">{{ $covered }} / {{ $total }} keywords covered · priority {{ $row->priority_score ?? '—' }}</div>
                    </div>
                    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full {{ $bg }}" style="width: {{ (int) ($coverage * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-xs text-slate-400">No topic clusters yet for this niche.</p>
            @endforelse
        </div>
    @endif
</div>
