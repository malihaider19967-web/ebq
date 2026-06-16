<div wire:poll.5s class="space-y-5">
    @php
        /**
         * @var array $rows
         * @var array $summary
         */
        $relTime = function ($when): string {
            if (! $when) return '—';
            try { return \Illuminate\Support\Carbon::parse($when)->diffForHumans(); }
            catch (\Throwable) { return '—'; }
        };
        $statusBadge = [
            'running'    => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
            'finalizing' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
            'completed'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            'aborted'    => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
            'failed'     => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
            'never'      => 'bg-slate-100 text-slate-500 dark:bg-slate-700/40 dark:text-slate-400',
        ];
        $statusLabel = [
            'running' => 'Crawling', 'finalizing' => 'Computing', 'completed' => 'Ready',
            'aborted' => 'Aborted', 'failed' => 'Failed', 'never' => 'Never crawled',
        ];
    @endphp

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            ['Domains', $summary['sites'], 'text-slate-900 dark:text-slate-100'],
            ['Crawling', $summary['running'], 'text-blue-600 dark:text-blue-400'],
            ['Ready', $summary['completed'], 'text-emerald-600 dark:text-emerald-400'],
            ['Queue backlog', number_format($summary['queue_depth']), $summary['queue_depth'] > 500 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100'],
            ['Pages crawled', number_format($summary['crawled_pages']), 'text-slate-900 dark:text-slate-100'],
            ['Open issues', number_format($summary['open_findings']), 'text-slate-900 dark:text-slate-100'],
        ] as [$label, $value, $color])
            <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800">
                <div class="text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ $label }}</div>
                <div class="mt-1 text-xl font-bold {{ $color }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    {{-- Per-domain table --}}
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400 dark:bg-slate-900/40">
                <tr>
                    <th class="px-4 py-2.5 text-left font-medium">Domain</th>
                    <th class="px-4 py-2.5 text-left font-medium">Website / Client</th>
                    <th class="px-4 py-2.5 text-left font-medium">Status</th>
                    <th class="px-4 py-2.5 text-left font-medium">Progress</th>
                    <th class="px-4 py-2.5 text-right font-medium">Crawled</th>
                    <th class="px-4 py-2.5 text-right font-medium">Errors</th>
                    <th class="px-4 py-2.5 text-right font-medium">Issues</th>
                    <th class="px-4 py-2.5 text-right font-medium">Health</th>
                    <th class="px-4 py-2.5 text-right font-medium">Subs / Cap</th>
                    <th class="px-4 py-2.5 text-left font-medium">Last activity</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse ($rows as $r)
                    @php
                        $st = $r['status'];
                        $isLive = in_array($st, ['running', 'finalizing'], true);
                        // Progress toward this run's cap window. pages_seen is shared;
                        // the cap window bounds it (a cap-1000 user's run still seen the full crawl).
                        $target = max($r['cap'], 1);
                        $done = $st === 'finalizing' ? $target : min($r['seen'], $target);
                        $pct = $r['cap'] > 0 ? min(100, (int) round($done / $target * 100)) : ($st === 'completed' ? 100 : 0);
                    @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30">
                        <td class="px-4 py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ $r['domain'] }}</td>
                        <td class="px-4 py-2.5">
                            @forelse ($r['clients'] as $c)
                                <div class="text-xs text-slate-700 dark:text-slate-300">
                                    {{ $c['website'] }} <span class="text-slate-400">· {{ $c['owner'] }}</span>
                                </div>
                            @empty
                                <span class="text-xs text-slate-400">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusBadge[$st] ?? $statusBadge['never'] }}">
                                @if ($isLive)<span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current"></span>@endif
                                {{ $statusLabel[$st] ?? $st }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5">
                            @if ($isLive || $st === 'completed')
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                        <div class="h-full rounded-full {{ $st === 'completed' ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="tabular-nums text-xs text-slate-500">{{ number_format(min($r['seen'], $r['cap'] ?: $r['seen'])) }}/{{ number_format($r['cap']) }}</span>
                                </div>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($r['crawled']) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums {{ $r['errors'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400' }}">{{ number_format($r['errors']) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($r['findings']) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            @if ($r['health'] !== null)
                                <span @class([
                                    'font-semibold',
                                    'text-emerald-600 dark:text-emerald-400' => $r['health'] >= 80,
                                    'text-amber-600 dark:text-amber-400' => $r['health'] >= 50 && $r['health'] < 80,
                                    'text-red-600 dark:text-red-400' => $r['health'] < 50,
                                ])>{{ $r['health'] }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-slate-500">{{ $r['subscribers'] }} / {{ number_format($r['cap']) }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $relTime($r['finished_at'] ?? $r['started_at']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-8 text-center text-sm text-slate-400">No crawl sites yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-[11px] text-slate-400">Live — refreshes every 5s. Queue backlog is the shared <code>crawl</code> queue depth across all domains.</p>
</div>
