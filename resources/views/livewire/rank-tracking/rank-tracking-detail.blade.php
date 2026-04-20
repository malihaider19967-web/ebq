<div>
    @if (! $keyword)
        <div class="rounded-xl border border-slate-200 bg-white p-10 text-center dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500">Tracked keyword not found or you do not have access.</p>
            <a href="{{ route('rank-tracking.index') }}" class="mt-3 inline-block text-xs font-semibold text-indigo-600 hover:underline">← Back to Rank Tracking</a>
        </div>
    @else
        @if (session('rank_tracking_status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-medium text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('rank_tracking_status') }}
            </div>
        @endif

        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('rank-tracking.index') }}" class="mb-2 inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500 hover:text-indigo-600">
                    ← Rank Tracking
                </a>
                <h1 class="text-2xl font-bold tracking-tight">{{ $keyword->keyword }}</h1>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <span>{{ $keyword->target_domain }}</span>
                    <span>·</span>
                    <span class="uppercase">{{ $keyword->country }} / {{ $keyword->language }}</span>
                    <span>·</span>
                    <span>{{ ucfirst($keyword->device) }}</span>
                    <span>·</span>
                    <span class="capitalize">{{ $keyword->search_type }}</span>
                    @if ($keyword->location)<span>·</span><span>{{ $keyword->location }}</span>@endif
                    <span>·</span>
                    <span>Top {{ $keyword->depth }}</span>
                    <span>·</span>
                    <span>Every {{ $keyword->check_interval_hours }}h</span>
                </div>
            </div>
            <button wire:click="recheck" type="button"
                class="inline-flex h-9 items-center gap-1.5 rounded-md bg-indigo-600 px-3.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                Force re-check
            </button>
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Current rank</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-slate-100">
                    {{ $keyword->current_position ? '#'.$keyword->current_position : '—' }}
                </div>
                @if ($keyword->position_change > 0)
                    <div class="mt-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">▲ {{ $keyword->position_change }} from last check</div>
                @elseif ($keyword->position_change < 0)
                    <div class="mt-0.5 text-[11px] font-semibold text-red-600 dark:text-red-400">▼ {{ abs($keyword->position_change) }} from last check</div>
                @endif
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Best ever</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                    {{ $keyword->best_position ? '#'.$keyword->best_position : '—' }}
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Initial rank</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-slate-700 dark:text-slate-300">
                    {{ $keyword->initial_position ? '#'.$keyword->initial_position : '—' }}
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Last checked</div>
                <div class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    {{ $keyword->last_checked_at ? $keyword->last_checked_at->diffForHumans() : '—' }}
                </div>
                <div class="mt-0.5 text-[11px] text-slate-400">
                    Next: {{ $keyword->next_check_at ? $keyword->next_check_at->diffForHumans() : '—' }}
                </div>
            </div>
        </div>

        @if (count($chartPoints) > 1)
            <div class="mb-5 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-2 text-xs font-semibold text-slate-900 dark:text-slate-100">Position history</div>
                @php
                    $positions = array_column($chartPoints, 'y');
                    $positions = array_filter($positions, fn ($v) => $v !== null);
                    $maxY = $positions ? max(max($positions), 20) : 20;
                    $count = count($chartPoints);
                @endphp
                <svg viewBox="0 0 {{ max(100, $count * 10) }} 100" class="h-32 w-full">
                    <polyline
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1"
                        class="text-indigo-500"
                        points="@foreach ($chartPoints as $idx => $p){{ $idx * 10 }},{{ $p['y'] !== null ? round(($p['y'] / $maxY) * 95) : 100 }} @endforeach" />
                    @foreach ($chartPoints as $idx => $p)
                        @if ($p['y'] !== null)
                            <circle cx="{{ $idx * 10 }}" cy="{{ round(($p['y'] / $maxY) * 95) }}" r="1.5" class="fill-indigo-500" />
                        @endif
                    @endforeach
                </svg>
                <div class="mt-1 flex justify-between text-[10px] text-slate-400">
                    <span>Lower = better. Max shown: #{{ $maxY }}</span>
                    <span>{{ $count }} checks</span>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 text-xs font-semibold text-slate-900 dark:border-slate-800 dark:text-slate-100">
                        Check history
                    </div>
                    @if ($snapshots->isEmpty())
                        <div class="px-4 py-6 text-center text-xs text-slate-500">No checks recorded yet.</div>
                    @else
                        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($snapshots as $snap)
                                <li>
                                    <button wire:click="selectSnapshot({{ $snap->id }})" type="button"
                                        @class([
                                            'flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-xs transition',
                                            'bg-indigo-50 dark:bg-indigo-500/10' => $selected && $selected->id === $snap->id,
                                            'hover:bg-slate-50 dark:hover:bg-slate-800/50' => ! $selected || $selected->id !== $snap->id,
                                        ])>
                                        <div>
                                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ $snap->checked_at->format('M d, Y H:i') }}
                                            </div>
                                            <div class="mt-0.5 text-[10px] text-slate-500">
                                                {{ $snap->checked_at->diffForHumans() }}
                                                @if ($snap->forced)<span class="ml-1 rounded bg-amber-100 px-1 text-[9px] font-semibold uppercase text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">forced</span>@endif
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            @if ($snap->status === 'ok')
                                                @if ($snap->position)
                                                    <span @class([
                                                        'inline-flex rounded-full px-2 py-px text-[11px] font-semibold tabular-nums',
                                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $snap->position <= 3,
                                                        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' => $snap->position > 3 && $snap->position <= 10,
                                                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' => $snap->position > 10 && $snap->position <= 20,
                                                        'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => $snap->position > 20,
                                                    ])>#{{ $snap->position }}</span>
                                                @else
                                                    <span class="text-[11px] text-slate-400">Not in top {{ $keyword->depth }}</span>
                                                @endif
                                            @else
                                                <span class="rounded bg-red-50 px-1.5 py-px text-[10px] font-semibold text-red-600 dark:bg-red-500/10 dark:text-red-400">{{ $snap->status }}</span>
                                            @endif
                                        </div>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="mt-3">{{ $snapshots->links() }}</div>
            </div>

            <div class="lg:col-span-3">
                @if (! $selected)
                    <div class="rounded-xl border border-slate-200 bg-white p-8 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900">
                        Pick a check on the left to see the SERP snapshot.
                    </div>
                @else
                    <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-3 dark:border-slate-800">
                            <div>
                                <div class="text-xs font-semibold text-slate-900 dark:text-slate-100">
                                    SERP snapshot · {{ $selected->checked_at->format('M d, Y H:i') }}
                                </div>
                                <div class="mt-0.5 text-[11px] text-slate-500">
                                    @if ($selected->total_results)
                                        {{ number_format($selected->total_results) }} results
                                    @endif
                                    @if ($selected->search_time)· {{ $selected->search_time }}s @endif
                                    @if ($selected->serp_features)
                                        · Features:
                                        @foreach ((array) $selected->serp_features as $feat)
                                            <span class="rounded bg-slate-100 px-1.5 py-px text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $feat }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($selected->status !== 'ok')
                            <div class="px-5 py-4 text-xs text-red-600 dark:text-red-400">
                                Check failed: {{ $selected->error ?? 'Unknown error' }}
                            </div>
                        @else
                            @if ($selected->position && $selected->url)
                                <div class="border-b border-slate-200 px-5 py-3 dark:border-slate-800">
                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Your listing</div>
                                    <div class="mt-1 flex items-start gap-3">
                                        <span class="mt-0.5 inline-flex h-6 shrink-0 items-center rounded-full bg-indigo-600 px-2 text-[11px] font-semibold text-white">#{{ $selected->position }}</span>
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $selected->title ?? '—' }}</div>
                                            <a href="{{ $selected->url }}" target="_blank" rel="noopener" class="truncate text-[11px] text-indigo-600 hover:underline dark:text-indigo-400">{{ $selected->url }}</a>
                                            @if ($selected->snippet)
                                                <div class="mt-1 text-[11px] text-slate-600 dark:text-slate-400">{{ $selected->snippet }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @php
                                $top = (array) ($selected->top_results ?? []);
                                $targetDomain = strtolower(preg_replace('/^www\./', '', (string) $keyword->target_domain));
                                $competitorDomains = collect((array) ($keyword->competitors ?? []))
                                    ->map(fn ($d) => strtolower(preg_replace('/^www\./', '', (string) $d)))
                                    ->filter()
                                    ->values()
                                    ->all();
                            @endphp

                            @if (! empty($top))
                                <div class="px-5 py-3">
                                    <div class="mb-2 flex items-center justify-between">
                                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">All sites ranked for this keyword</div>
                                        <div class="text-[10px] text-slate-400">{{ count($top) }} results</div>
                                    </div>
                                    <ol class="space-y-2">
                                        @foreach ($top as $row)
                                            @php
                                                $link = (string) ($row['link'] ?? '');
                                                $host = '';
                                                if ($link !== '') {
                                                    $h = parse_url($link, PHP_URL_HOST);
                                                    if (is_string($h)) {
                                                        $host = strtolower(preg_replace('/^www\./', '', $h));
                                                    }
                                                }
                                                $isYou = $host !== '' && $host === $targetDomain;
                                                $isCompetitor = ! $isYou && in_array($host, $competitorDomains, true);
                                            @endphp
                                            <li @class([
                                                'flex items-start gap-3 rounded-lg border px-3 py-2',
                                                'border-indigo-200 bg-indigo-50 dark:border-indigo-500/30 dark:bg-indigo-500/10' => $isYou,
                                                'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10' => $isCompetitor,
                                                'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' => ! $isYou && ! $isCompetitor,
                                            ])>
                                                <span @class([
                                                    'mt-0.5 inline-flex h-6 w-10 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold tabular-nums',
                                                    'bg-indigo-600 text-white' => $isYou,
                                                    'bg-amber-500 text-white' => $isCompetitor,
                                                    'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' => ! $isYou && ! $isCompetitor,
                                                ])>#{{ $row['position'] ?? '—' }}</span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="truncate text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $row['title'] ?? '—' }}</span>
                                                        @if ($isYou)<span class="rounded bg-indigo-600 px-1.5 py-px text-[9px] font-bold uppercase text-white">You</span>@endif
                                                        @if ($isCompetitor)<span class="rounded bg-amber-500 px-1.5 py-px text-[9px] font-bold uppercase text-white">Competitor</span>@endif
                                                    </div>
                                                    @if ($link)
                                                        <a href="{{ $link }}" target="_blank" rel="noopener" class="block truncate text-[10px] text-emerald-700 hover:underline dark:text-emerald-400">{{ $link }}</a>
                                                    @endif
                                                    @if (! empty($row['snippet']))
                                                        <div class="mt-0.5 text-[11px] text-slate-600 dark:text-slate-400">{{ $row['snippet'] }}</div>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            @endif

                            @if (! empty($selected->competitor_positions))
                                <div class="border-t border-slate-200 px-5 py-3 dark:border-slate-800">
                                    <div class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Competitors tracked</div>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach ((array) $selected->competitor_positions as $c)
                                            <div class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-1.5 text-xs dark:border-slate-800">
                                                <span class="truncate text-slate-700 dark:text-slate-300">{{ $c['domain'] ?? '—' }}</span>
                                                @if (! empty($c['position']))
                                                    <span class="rounded-full bg-amber-50 px-2 py-px text-[10px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">#{{ $c['position'] }}</span>
                                                @else
                                                    <span class="text-[10px] text-slate-400">not ranked</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (! empty($selected->people_also_ask))
                                <div class="border-t border-slate-200 px-5 py-3 dark:border-slate-800">
                                    <div class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">People also ask</div>
                                    <ul class="space-y-1 text-xs text-slate-700 dark:text-slate-300">
                                        @foreach ((array) $selected->people_also_ask as $paa)
                                            <li>• {{ $paa['question'] ?? ($paa['title'] ?? '—') }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (! empty($selected->related_searches))
                                <div class="border-t border-slate-200 px-5 py-3 dark:border-slate-800">
                                    <div class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Related searches</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ((array) $selected->related_searches as $rel)
                                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $rel['query'] ?? '—' }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
