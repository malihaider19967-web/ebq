<div>
    <div class="mb-4 max-w-md">
        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Domain</label>
        <input wire:model.live.debounce.500ms="domain" type="text" placeholder="example.com"
            class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
    </div>

    @if ($domain === '')
        <p class="text-xs text-slate-400">Enter a domain — we'll list keywords it's ranking for.</p>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Keyword</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Rank</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">URL</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row['keyword'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['rank'] }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500"><a href="{{ $row['url'] }}" target="_blank" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ \Illuminate\Support\Str::limit($row['url'], 60) }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-8 text-center text-xs text-slate-400">No SERP rows for this domain yet — crawl more keywords to populate.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
