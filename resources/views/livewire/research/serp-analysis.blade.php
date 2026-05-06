<div>
    <form wire:submit.prevent="fetch" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Keyword</label>
            <input wire:model="query" type="text" placeholder="best running shoes"
                class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
        </div>
        <div class="w-28">
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Country</label>
            <input wire:model="country" type="text" maxlength="2" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm uppercase shadow-sm dark:border-slate-700 dark:bg-slate-800" />
        </div>
        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">
            <span wire:loading.remove>Fetch SERP</span>
            <span wire:loading>Fetching…</span>
        </button>
    </form>

    @if ($status)
        <div class="mb-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-800/60">{{ $status }}</div>
    @endif

    @if ($snapshot)
        <div class="mb-2 text-xs text-slate-500">Snapshot fetched {{ $snapshot->fetched_at?->diffForHumans() }} · {{ $snapshot->device }} · {{ strtoupper($snapshot->country) }}</div>
        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="w-12 px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">#</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Result</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Quality</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                    @foreach ($results as $row)
                        <tr>
                            <td class="px-3 py-2 tabular-nums">{{ $row->rank }}</td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ $row->title ?: $row->domain }}</div>
                                <a href="{{ $row->url }}" target="_blank" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">{{ $row->domain }}</a>
                                @if ($row->snippet)
                                    <p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($row->snippet, 200) }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if ($row->is_low_quality)
                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Low</span>
                                @else
                                    <span class="text-[10px] text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($features->count() > 0)
            <div class="mt-4 space-y-2">
                <h2 class="text-xs font-semibold uppercase text-slate-500">SERP Features</h2>
                <ul class="space-y-1 text-xs">
                    @foreach ($features as $feature)
                        <li class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 dark:border-slate-800 dark:bg-slate-900">
                            <span class="font-semibold uppercase tracking-wide">{{ $feature->feature_type }}</span>
                            <span class="ml-2 text-slate-500">{{ is_array($feature->payload) ? count($feature->payload) : 1 }} item(s)</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @else
        <p class="text-xs text-slate-400">Run a fetch to see the latest SERP for this keyword.</p>
    @endif
</div>
