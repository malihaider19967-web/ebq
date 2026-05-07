<div>
    <div class="mb-4 max-w-md">
        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Competitor domain</label>
        <input wire:model.live.debounce.500ms="domain" type="text" placeholder="competitor.com"
            class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
    </div>

    @if ($mode === 'empty')
        <p class="text-xs text-slate-400">Enter a competitor domain. If we've scraped it, you'll see the full crawl breakdown; otherwise we fall back to the keywords it ranks for in our SERP snapshots.</p>

    @elseif ($mode === 'scan')
        <div class="mb-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
            Showing data from a full crawl finished {{ $scan->finished_at?->diffForHumans() }} · {{ number_format($scan->page_count) }} pages, {{ number_format($scan->external_page_count) }} external.
        </div>

        @if ($topics->isNotEmpty())
            <div class="mb-4 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="border-b border-slate-200 px-4 py-2 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800">Topics they cover</div>
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Topic</th>
                            <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Pages</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        @foreach ($topics as $topic)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $topic->name }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $topic->page_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($topExternalDomains->isNotEmpty())
            <div class="mb-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                <div class="text-xs font-semibold uppercase text-slate-500">Most-linked external domains</div>
                <ul class="mt-3 grid grid-cols-1 gap-1 text-xs sm:grid-cols-2">
                    @foreach ($topExternalDomains as $row)
                        <li class="flex items-center justify-between rounded-md bg-slate-50 px-2.5 py-1 dark:bg-slate-800/60">
                            <span class="truncate font-mono">{{ $row->to_domain }}</span>
                            <span class="tabular-nums text-slate-500">{{ $row->link_count }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
            <div class="border-b border-slate-200 px-4 py-2 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800">Top pages by word count</div>
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Page</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Words</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Depth</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    @foreach ($pages as $page)
                        <tr>
                            <td class="px-3 py-2">
                                <a href="{{ $page->url }}" target="_blank" rel="noopener" class="block truncate text-indigo-600 hover:underline dark:text-indigo-400">{{ $page->title ?: $page->url }}</a>
                                @if ($page->meta_description)
                                    <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ \Illuminate\Support\Str::limit($page->meta_description, 140) }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($page->word_count) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $page->depth }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @elseif ($mode === 'serp')
        <div class="mb-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
            We haven't scraped this domain yet — showing keywords it ranks for from our SERP snapshots. Run a full scrape from <a href="{{ route('admin.research.competitor-scans.create') }}" class="text-indigo-600 hover:underline dark:text-indigo-400">admin → competitor scans</a> for the richer view.
        </div>
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
                    @forelse ($serpKeywords as $kw)
                        <tr>
                            <td class="px-3 py-2">{{ $kw->query }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $kw->search_volume !== null ? number_format($kw->search_volume) : '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $kw->difficulty_score ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-8 text-center text-xs text-slate-400">No SERP data yet for this domain.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
