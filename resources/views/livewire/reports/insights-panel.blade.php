<div class="space-y-4" wire:key="insights-{{ $websiteId }}-{{ $tab }}">
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold tracking-tight">Insights</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Action-list views over the last 28 days of Search Console + indexing data.</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                ['key' => 'cannibalization', 'label' => 'Cannibalizations', 'count' => $counts['cannibalizations'], 'tone' => 'amber'],
                ['key' => 'striking_distance', 'label' => 'Striking distance', 'count' => $counts['striking_distance'], 'tone' => 'indigo'],
                ['key' => 'indexing_fails', 'label' => 'Indexing fails w/ traffic', 'count' => $counts['indexing_fails_with_traffic'], 'tone' => 'red'],
                ['key' => 'content_decay', 'label' => 'Content decay', 'count' => $counts['content_decay'], 'tone' => 'slate'],
                ['key' => 'audit_performance', 'label' => 'Audit vs traffic', 'count' => null, 'tone' => 'rose'],
                ['key' => 'backlink_impact', 'label' => 'Backlink impact', 'count' => null, 'tone' => 'emerald'],
            ] as $t)
                @php($active = $tab === $t['key'])
                <button type="button" wire:click="setTab('{{ $t['key'] }}')"
                    @class([
                        'flex flex-col items-start rounded-lg border px-3 py-2.5 text-left transition',
                        'border-indigo-300 bg-indigo-50 dark:border-indigo-500/40 dark:bg-indigo-500/10' => $active,
                        'border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50 dark:hover:bg-slate-800' => ! $active,
                    ])>
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $t['label'] }}</span>
                    <span @class([
                        'mt-1 text-xl font-bold tabular-nums',
                        'text-amber-600 dark:text-amber-400' => $t['tone'] === 'amber',
                        'text-indigo-600 dark:text-indigo-400' => $t['tone'] === 'indigo',
                        'text-red-600 dark:text-red-400' => $t['tone'] === 'red',
                        'text-slate-700 dark:text-slate-200' => $t['tone'] === 'slate',
                        'text-rose-600 dark:text-rose-400' => $t['tone'] === 'rose',
                        'text-emerald-600 dark:text-emerald-400' => $t['tone'] === 'emerald',
                    ])>{{ $t['count'] === null ? 'View' : number_format($t['count']) }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @if (! $hasAccess)
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
            Select a website to view insights.
        </div>
    @elseif ($tab === 'cannibalization')
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <h3 class="text-sm font-semibold">Keyword cannibalization</h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Queries where two or more of your pages split clicks — consolidate content or re-target the weaker URLs.</p>
            @if (empty($data['cannibalization']))
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">No cannibalization detected in the last 28 days.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <tr>
                                <th class="py-2 pr-3 font-semibold">Query</th>
                                <th class="py-2 pr-3 font-semibold">Primary page</th>
                                <th class="py-2 pr-3 text-right font-semibold">Pages</th>
                                <th class="py-2 pr-3 text-right font-semibold">Clicks</th>
                                <th class="py-2 pr-3 text-right font-semibold">Impressions</th>
                                <th class="py-2 font-semibold">Competing pages (share %)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($data['cannibalization'] as $row)
                                <tr>
                                    <td class="py-2 pr-3 font-medium text-slate-800 dark:text-slate-200">{{ $row['query'] }}</td>
                                    <td class="py-2 pr-3 max-w-[280px] truncate text-slate-600 dark:text-slate-300" title="{{ $row['primary_page'] }}">{{ $row['primary_page'] }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $row['page_count'] }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['total_clicks']) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['total_impressions']) }}</td>
                                    <td class="py-2">
                                        <ul class="space-y-0.5">
                                            @foreach ($row['competing_pages'] as $p)
                                                <li class="max-w-[360px] truncate text-slate-500 dark:text-slate-400" title="{{ $p['page'] }}">
                                                    <span class="tabular-nums text-amber-600 dark:text-amber-400">{{ $p['share'] }}%</span>
                                                    <span class="ml-2">{{ $p['page'] }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif ($tab === 'striking_distance')
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <h3 class="text-sm font-semibold">Striking-distance keywords</h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Queries at positions 5–20 with strong impressions and below-curve CTR — the fastest wins on your content calendar.</p>
            @if (empty($data['striking_distance']))
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">No striking-distance opportunities detected.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <tr>
                                <th class="py-2 pr-3 font-semibold">Query</th>
                                <th class="py-2 pr-3 text-right font-semibold">Position</th>
                                <th class="py-2 pr-3 text-right font-semibold">Impressions</th>
                                <th class="py-2 pr-3 text-right font-semibold">Clicks</th>
                                <th class="py-2 pr-3 text-right font-semibold">CTR</th>
                                <th class="py-2 pr-3 text-right font-semibold">Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($data['striking_distance'] as $row)
                                <tr>
                                    <td class="py-2 pr-3 font-medium text-slate-800 dark:text-slate-200">{{ $row['query'] }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $row['position'] }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['impressions']) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['clicks']) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $row['ctr'] }}%</td>
                                    <td class="py-2 pr-3 text-right tabular-nums font-semibold text-indigo-600 dark:text-indigo-400">{{ $row['score'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif ($tab === 'indexing_fails')
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <h3 class="text-sm font-semibold">Indexing failures with live traffic</h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Pages with a non-PASS Google verdict that still received impressions in the last 14 days — urgent action required.</p>
            @if (empty($data['indexing_fails']))
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">No failing pages with recent traffic.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <tr>
                                <th class="py-2 pr-3 font-semibold">Page</th>
                                <th class="py-2 pr-3 font-semibold">Verdict</th>
                                <th class="py-2 pr-3 font-semibold">Coverage</th>
                                <th class="py-2 pr-3 text-right font-semibold">Clicks (14d)</th>
                                <th class="py-2 pr-3 text-right font-semibold">Impressions (14d)</th>
                                <th class="py-2 pr-3 font-semibold">Last crawl</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($data['indexing_fails'] as $row)
                                <tr>
                                    <td class="py-2 pr-3 max-w-[320px] truncate text-slate-800 dark:text-slate-200" title="{{ $row['page'] }}">{{ $row['page'] }}</td>
                                    <td class="py-2 pr-3"><span class="rounded bg-red-50 px-1.5 py-0.5 font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-400">{{ $row['verdict'] }}</span></td>
                                    <td class="py-2 pr-3 text-slate-600 dark:text-slate-300">{{ $row['coverage_state'] }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['recent_clicks']) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['recent_impressions']) }}</td>
                                    <td class="py-2 pr-3 text-slate-500 dark:text-slate-400">{{ $row['last_crawl_at'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif ($tab === 'audit_performance')
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <h3 class="text-sm font-semibold">Audit vs. performance</h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Pages with poor Lighthouse performance scores (under 70) that still attract real search impressions — technical debt measurably costing traffic.</p>
            @if (empty($data['audit_performance']))
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">No underperforming audited pages detected.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <tr>
                                <th class="py-2 pr-3 font-semibold">Page</th>
                                <th class="py-2 pr-3 text-right font-semibold">Mobile score</th>
                                <th class="py-2 pr-3 text-right font-semibold">Desktop score</th>
                                <th class="py-2 pr-3 text-right font-semibold">LCP (mob)</th>
                                <th class="py-2 pr-3 text-right font-semibold">CLS (mob)</th>
                                <th class="py-2 pr-3 text-right font-semibold">Impressions (28d)</th>
                                <th class="py-2 pr-3 text-right font-semibold">Clicks (28d)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($data['audit_performance'] as $row)
                                <tr>
                                    <td class="py-2 pr-3 max-w-[320px] truncate text-slate-800 dark:text-slate-200" title="{{ $row['page'] }}">{{ $row['page'] }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">
                                        @if ($row['performance_score_mobile'] === null)
                                            <span class="text-slate-400">—</span>
                                        @else
                                            <span @class(['font-semibold', 'text-red-600 dark:text-red-400' => $row['performance_score_mobile'] < 50, 'text-amber-600 dark:text-amber-400' => $row['performance_score_mobile'] >= 50])>{{ $row['performance_score_mobile'] }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $row['performance_score_desktop'] ?? '—' }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $row['lcp_ms_mobile'] !== null ? number_format($row['lcp_ms_mobile']).'ms' : '—' }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $row['cls_mobile'] !== null ? number_format($row['cls_mobile'], 3) : '—' }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['impressions']) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['clicks']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif ($tab === 'backlink_impact')
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <h3 class="text-sm font-semibold">Backlink impact</h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Per target page, clicks in the 28 days <em>after</em> the latest tracked backlink vs the 28 days before — sorted by biggest lift.</p>
            @if (empty($data['backlink_impact']))
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">No backlinks with measurable pre/post traffic data yet.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <tr>
                                <th class="py-2 pr-3 font-semibold">Target page</th>
                                <th class="py-2 pr-3 text-right font-semibold">Links</th>
                                <th class="py-2 pr-3 text-right font-semibold">Avg DA</th>
                                <th class="py-2 pr-3 font-semibold">Latest link</th>
                                <th class="py-2 pr-3 text-right font-semibold">Pre clicks</th>
                                <th class="py-2 pr-3 text-right font-semibold">Post clicks</th>
                                <th class="py-2 pr-3 text-right font-semibold">Δ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($data['backlink_impact'] as $row)
                                <tr>
                                    <td class="py-2 pr-3 max-w-[320px] truncate text-slate-800 dark:text-slate-200" title="{{ $row['target_page_url'] }}">{{ $row['target_page_url'] }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $row['backlink_count'] }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $row['avg_da'] ?? '—' }}</td>
                                    <td class="py-2 pr-3 text-slate-500 dark:text-slate-400">{{ $row['latest_tracked_date'] }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['pre_clicks']) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['post_clicks']) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">
                                        <span @class([
                                            'font-semibold',
                                            'text-emerald-600 dark:text-emerald-400' => $row['clicks_change'] > 0,
                                            'text-red-600 dark:text-red-400' => $row['clicks_change'] < 0,
                                            'text-slate-400' => $row['clicks_change'] === 0,
                                        ])>{{ $row['clicks_change'] >= 0 ? '+' : '' }}{{ $row['clicks_change'] }}{{ $row['clicks_change_percent'] !== null ? ' ('.$row['clicks_change_percent'].'%)' : '' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif ($tab === 'content_decay')
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <h3 class="text-sm font-semibold">Content decay</h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Pages losing clicks 28d-over-28d while still attracting impressions. Indexing verdict is shown so you can distinguish ranking decay from de-indexing.</p>
            @if (empty($data['content_decay']['pages']))
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">No decay detected — or no 28-day comparison data yet.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <tr>
                                <th class="py-2 pr-3 font-semibold">Page</th>
                                <th class="py-2 pr-3 text-right font-semibold">Clicks (28d)</th>
                                <th class="py-2 pr-3 text-right font-semibold">Prev 28d</th>
                                <th class="py-2 pr-3 text-right font-semibold">Δ 28d</th>
                                <th class="py-2 pr-3 text-right font-semibold">YoY</th>
                                <th class="py-2 pr-3 font-semibold">Verdict</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($data['content_decay']['pages'] as $row)
                                <tr>
                                    <td class="py-2 pr-3 max-w-[320px] truncate text-slate-800 dark:text-slate-200" title="{{ $row['page'] }}">{{ $row['page'] }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['current_clicks']) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($row['previous_clicks']) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums font-semibold text-red-600 dark:text-red-400">{{ $row['clicks_change_percent'] }}%</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">
                                        @if (! $data['content_decay']['has_yoy_history'])
                                            <span class="text-slate-400 dark:text-slate-500">insufficient history</span>
                                        @elseif ($row['yoy_change_percent'] === null)
                                            <span class="text-slate-400 dark:text-slate-500">—</span>
                                        @else
                                            <span @class([
                                                'font-semibold',
                                                'text-red-600 dark:text-red-400' => $row['yoy_change_percent'] < 0,
                                                'text-emerald-600 dark:text-emerald-400' => $row['yoy_change_percent'] >= 0,
                                            ])>{{ $row['yoy_change_percent'] }}%</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-3">
                                        @if ($row['verdict'] === 'PASS')
                                            <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">PASS</span>
                                        @elseif ($row['verdict'])
                                            <span class="rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-400">{{ $row['verdict'] }}</span>
                                        @else
                                            <span class="text-slate-400 dark:text-slate-500">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
