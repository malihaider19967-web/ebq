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
        <p class="text-xs text-slate-400">Pick a niche to see topics across our competitor scans.</p>
    @elseif ($keywordCount === 0)
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
            No keywords are mapped to this niche yet. Niche linkage happens automatically after each successful competitor scan via MapScanKeywordsToNichesJob. If you've already scraped sites, the linkage runs the next time the scan flushes — or you can re-run a scan to backfill.
        </div>
    @else
        <p class="mb-3 text-xs text-slate-500">{{ number_format($keywordCount) }} keyword(s) in this niche across our scan corpus.</p>

        @if ($topics->isNotEmpty())
            <div class="mb-4 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="border-b border-slate-200 px-4 py-2 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800">Topics covered by competitors</div>
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Topic</th>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Top phrases</th>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Found on</th>
                            <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Pages</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        @foreach ($topics as $topic)
                            @php
                                $phrases = collect((array) $topic->top_keyword_ids)
                                    ->map(fn ($kid) => $topicKeywordPhrases[(int) $kid] ?? null)
                                    ->filter()
                                    ->take(5);
                            @endphp
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $topic->name }}</td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $phrases->implode(' · ') ?: '—' }}</td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $topic->scan?->seed_domain ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $topic->page_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="mb-4 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                Niche keywords exist, but no competitor topics centroid into this niche yet. Run more competitor scans (especially on sites that target these keywords) and the topics will appear.
            </div>
        @endif

        @if ($rankings->isNotEmpty())
            <div class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="border-b border-slate-200 px-4 py-2 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800">Best-covered keywords across scanned competitors</div>
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Keyword</th>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Competitor</th>
                            <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Total occurrences</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        @foreach ($rankings as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $row->keyword?->query ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $row->scan?->seed_domain ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row->total_occurrences) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
