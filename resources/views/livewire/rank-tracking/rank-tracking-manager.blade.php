<div class="space-y-5">
    @if (session('rank_tracking_status'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
            class="flex items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-medium text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                {{ session('rank_tracking_status') }}
            </div>
            <button @click="show = false" class="text-emerald-700/70 hover:text-emerald-700 dark:text-emerald-400/70">×</button>
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Tracked</div>
            <div class="mt-1 text-xl font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ $stats['total'] }}</div>
            <div class="mt-0.5 text-[10px] text-slate-400">{{ $stats['active'] }} active</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Avg position</div>
            <div class="mt-1 text-xl font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ $stats['avg'] !== null ? '#'.$stats['avg'] : '—' }}</div>
            <div class="mt-0.5 text-[10px] text-slate-400">across ranked keywords</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Top 3</div>
            <div class="mt-1 text-xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $stats['top3'] }}</div>
            <div class="mt-0.5 text-[10px] text-slate-400">positions 1–3</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Top 10</div>
            <div class="mt-1 text-xl font-bold tabular-nums text-blue-600 dark:text-blue-400">{{ $stats['top10'] }}</div>
            <div class="mt-0.5 text-[10px] text-slate-400">first page</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Ranked</div>
            <div class="mt-1 text-xl font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ $stats['top100'] }}</div>
            <div class="mt-0.5 text-[10px] text-slate-400">found in SERP</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900">
            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Unranked</div>
            <div class="mt-1 text-xl font-bold tabular-nums text-slate-500">{{ $stats['unranked'] }}</div>
            <div class="mt-0.5 text-[10px] text-slate-400">outside tracked depth</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-1 flex-wrap items-center gap-2">
                <div class="relative w-full sm:w-64">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search keywords…"
                        class="h-8 w-full rounded-md border border-slate-200 bg-white pl-8 pr-2.5 text-xs placeholder-slate-400 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:placeholder-slate-500" />
                </div>
                <select wire:model.live="filterStatus" class="h-8 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">All status</option>
                    <option value="top3">Top 3</option>
                    <option value="top10">Top 10</option>
                    <option value="top100">Ranked</option>
                    <option value="unranked">Unranked</option>
                    <option value="active">Active</option>
                    <option value="paused">Paused</option>
                    <option value="failed">Failed</option>
                </select>
                <select wire:model.live="filterDevice" class="h-8 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">All devices</option>
                    <option value="desktop">Desktop</option>
                    <option value="mobile">Mobile</option>
                </select>
                <select wire:model.live="filterCountry" class="h-8 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">All countries</option>
                    @foreach ($countries as $code => $name)
                        <option value="{{ $code }}">{{ strtoupper($code) }} — {{ $name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterType" class="h-8 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">All types</option>
                    <option value="organic">Organic</option>
                    <option value="news">News</option>
                    <option value="images">Images</option>
                    <option value="videos">Videos</option>
                    <option value="shopping">Shopping</option>
                    <option value="maps">Maps</option>
                    <option value="scholar">Scholar</option>
                </select>
                @if ($search || $filterDevice || $filterCountry || $filterType || $filterStatus)
                    <button wire:click="clearFilters" class="h-8 rounded-md px-2.5 text-xs text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                        Clear
                    </button>
                @endif
            </div>
            <button wire:click="toggleForm" type="button"
                class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md bg-indigo-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                {{ $showForm ? 'Close form' : 'Add keyword' }}
            </button>
        </div>
    </div>

    {{-- Add form --}}
    @if ($showForm)
        <form wire:submit.prevent="addKeyword"
            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Track a new keyword</h3>
                <span class="text-[10px] uppercase tracking-wider text-slate-400">All options configurable</span>
            </div>

            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Keyword <span class="text-red-500">*</span></label>
                        <input wire:model="newKeyword" type="text" placeholder="e.g. best seo tools"
                            class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                        @error('newKeyword')<p class="mt-1 text-[11px] text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Target domain <span class="text-red-500">*</span></label>
                        <input wire:model="newTargetDomain" type="text" placeholder="example.com"
                            class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                        @error('newTargetDomain')<p class="mt-1 text-[11px] text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Specific URL (optional)</label>
                        <input wire:model="newTargetUrl" type="text" placeholder="https://example.com/page"
                            class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <div class="mb-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400">SERP targeting</div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Search engine</label>
                            <select wire:model="newSearchEngine" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <option value="google">Google</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Search type</label>
                            <select wire:model="newSearchType" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <option value="organic">Organic Search</option>
                                <option value="news">News</option>
                                <option value="images">Images</option>
                                <option value="videos">Videos</option>
                                <option value="shopping">Shopping</option>
                                <option value="maps">Maps / Places</option>
                                <option value="scholar">Scholar</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Device</label>
                            <select wire:model="newDevice" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <option value="desktop">Desktop</option>
                                <option value="mobile">Mobile</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Country</label>
                            <select wire:model="newCountry" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                @foreach ($countries as $code => $name)
                                    <option value="{{ $code }}">{{ $name }} ({{ strtoupper($code) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Language</label>
                            <select wire:model="newLanguage" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                @foreach ($languages as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Location (optional)</label>
                            <input wire:model="newLocation" type="text" placeholder="New York, NY, United States"
                                class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <div class="mb-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Search parameters</div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">SERP depth</label>
                            <select wire:model="newDepth" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <option value="10">Top 10</option>
                                <option value="20">Top 20</option>
                                <option value="30">Top 30</option>
                                <option value="50">Top 50</option>
                                <option value="100">Top 100</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Time filter</label>
                            <select wire:model="newTbs" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <option value="">Any time</option>
                                <option value="qdr:h">Past hour</option>
                                <option value="qdr:d">Past 24 hours</option>
                                <option value="qdr:w">Past week</option>
                                <option value="qdr:m">Past month</option>
                                <option value="qdr:y">Past year</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Re-check every (hours)</label>
                            <input wire:model="newIntervalHours" type="number" min="1" max="168"
                                class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-5">
                        <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                            <input wire:model="newAutocorrect" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Autocorrect queries
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                            <input wire:model="newSafeSearch" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Safe search
                        </label>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <div class="mb-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Organization</div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Competitor domains</label>
                            <input wire:model="newCompetitors" type="text" placeholder="competitor1.com, competitor2.com"
                                class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Tags</label>
                            <input wire:model="newTags" type="text" placeholder="homepage, brand, priority"
                                class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                            <textarea wire:model="newNotes" rows="2"
                                class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" wire:click="toggleForm"
                    class="h-8 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Cancel</button>
                <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="addKeyword"
                    class="inline-flex h-8 items-center gap-2 rounded-md bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-60">
                    <svg wire:loading wire:target="addKeyword" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"></circle><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4zm2 5.3A7.96 7.96 0 014 12H0c0 3.04 1.1 5.8 3 7.9l3-2.6z"></path></svg>
                    Add & run first check
                </button>
            </div>
        </form>
    @endif

    {{-- List --}}
    <div wire:loading.class="opacity-60" wire:target="search,filterDevice,filterCountry,filterType,filterStatus,sort">
        @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                                <th class="px-4 py-3 text-left">
                                    <button wire:click="sort('keyword')" class="inline-flex items-center gap-1 hover:text-slate-700 dark:hover:text-slate-200">
                                        Keyword
                                        @if ($sortBy === 'keyword')<span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left">Target</th>
                                <th class="px-4 py-3 text-left">Market</th>
                                <th class="px-4 py-3 text-right">
                                    <button wire:click="sort('current_position')" class="inline-flex items-center gap-1 hover:text-slate-700 dark:hover:text-slate-200">
                                        Rank
                                        @if ($sortBy === 'current_position')<span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-right">
                                    <button wire:click="sort('position_change')" class="inline-flex items-center gap-1 hover:text-slate-700 dark:hover:text-slate-200">
                                        Δ
                                        @if ($sortBy === 'position_change')<span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-right">
                                    <button wire:click="sort('best_position')" class="inline-flex items-center gap-1 hover:text-slate-700 dark:hover:text-slate-200">
                                        Best
                                        @if ($sortBy === 'best_position')<span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left">GSC (30d)</th>
                                <th class="px-4 py-3 text-left">
                                    <button wire:click="sort('last_checked_at')" class="inline-flex items-center gap-1 hover:text-slate-700 dark:hover:text-slate-200">
                                        Last check
                                        @if ($sortBy === 'last_checked_at')<span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($rows as $kw)
                                <tr wire:key="rtk-{{ $kw->id }}" class="group transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('rank-tracking.show', $kw->id) }}" wire:navigate
                                            class="block font-semibold text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-indigo-400">
                                            {{ $kw->keyword }}
                                        </a>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                            <span class="rounded bg-slate-100 px-1.5 py-px text-[9px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $kw->search_type }}</span>
                                            @if (! $kw->is_active)<span class="rounded bg-slate-200 px-1.5 py-px text-[9px] font-semibold uppercase text-slate-600 dark:bg-slate-700 dark:text-slate-300">Paused</span>@endif
                                            @if ($kw->last_status === 'failed')<span class="rounded bg-red-100 px-1.5 py-px text-[9px] font-semibold uppercase text-red-700 dark:bg-red-500/10 dark:text-red-400">Failed</span>@endif
                                            @php($risk = $serpRisk[$kw->id] ?? null)
                                            @if ($risk && $risk['at_risk'])
                                                <span class="rounded bg-amber-100 px-1.5 py-px text-[9px] font-semibold uppercase text-amber-700 dark:bg-amber-500/15 dark:text-amber-400" title="SERP has {{ implode(', ', $risk['features_present']) }} and we don't own the top result">SERP risk</span>
                                            @endif
                                            @if ($risk && $risk['lost_feature'])
                                                <span class="rounded bg-red-100 px-1.5 py-px text-[9px] font-semibold uppercase text-red-700 dark:bg-red-500/10 dark:text-red-400" title="Lost SERP feature: {{ implode(', ', $risk['features_lost']) }}">lost feature</span>
                                            @endif
                                            @foreach ((array) $kw->tags as $tag)
                                                <span class="rounded bg-indigo-50 px-1.5 py-px text-[9px] text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $tag }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                        <div class="font-medium">{{ $kw->target_domain }}</div>
                                        @if ($kw->current_url)
                                            <a href="{{ $kw->current_url }}" target="_blank" rel="noopener" class="block max-w-[240px] truncate text-[10px] text-emerald-700 hover:underline dark:text-emerald-400">{{ $kw->current_url }}</a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                        <div class="flex items-center gap-1.5">
                                            <span class="rounded bg-slate-100 px-1.5 py-px text-[10px] font-semibold uppercase dark:bg-slate-800">{{ $kw->country }}</span>
                                            <span class="text-[10px]">{{ $kw->language }}</span>
                                            <span class="text-[10px]">·</span>
                                            <span class="text-[10px] capitalize">{{ $kw->device }}</span>
                                        </div>
                                        @if ($kw->location)<div class="mt-0.5 text-[10px] text-slate-400">{{ $kw->location }}</div>@endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($kw->current_position)
                                            <span @class([
                                                'inline-flex min-w-[44px] justify-center rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums',
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $kw->current_position <= 3,
                                                'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' => $kw->current_position > 3 && $kw->current_position <= 10,
                                                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' => $kw->current_position > 10 && $kw->current_position <= 20,
                                                'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => $kw->current_position > 20,
                                            ])>#{{ $kw->current_position }}</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        @if ($kw->position_change > 0)
                                            <span class="inline-flex items-center gap-0.5 text-emerald-600 dark:text-emerald-400">▲{{ $kw->position_change }}</span>
                                        @elseif ($kw->position_change < 0)
                                            <span class="inline-flex items-center gap-0.5 text-red-600 dark:text-red-400">▼{{ abs($kw->position_change) }}</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ $kw->best_position ? '#'.$kw->best_position : '—' }}</td>
                                    <td class="px-4 py-3">
                                        @php $gsc = $gscByKeyword[mb_strtolower(trim($kw->keyword))] ?? null; @endphp
                                        @if ($gsc)
                                            <div class="text-[11px] text-slate-600 dark:text-slate-400">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="inline-flex items-center gap-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400" title="Matched against Google Search Console">GSC</span>
                                                    <span class="tabular-nums font-semibold text-slate-800 dark:text-slate-200">{{ number_format($gsc['clicks']) }}</span>
                                                    <span class="text-[10px] text-slate-400">clicks</span>
                                                </div>
                                                <div class="mt-0.5 text-[10px] text-slate-400">Avg #{{ $gsc['position'] ?? '—' }} · {{ number_format($gsc['impressions']) }} impr · 30d</div>
                                            </div>
                                        @else
                                            <span class="text-[10px] text-slate-400">No GSC match</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                        @if ($kw->last_checked_at)
                                            <div>{{ $kw->last_checked_at->diffForHumans() }}</div>
                                            <div class="text-[10px] text-slate-400">Next: {{ $kw->next_check_at ? $kw->next_check_at->diffForHumans() : '—' }}</div>
                                        @else
                                            <span class="text-amber-600 dark:text-amber-400">Pending first check</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="{{ route('rank-tracking.show', $kw->id) }}" wire:navigate
                                                class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1 text-[10px] font-semibold text-white shadow-sm hover:bg-indigo-700"
                                                title="View detail">
                                                View
                                                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                            </a>
                                            <button wire:click="recheck({{ $kw->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="recheck({{ $kw->id }})"
                                                class="rounded-md border border-slate-200 bg-white p-1.5 text-slate-500 hover:bg-slate-50 hover:text-indigo-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-indigo-400"
                                                title="Force re-check">
                                                <svg wire:loading.remove wire:target="recheck({{ $kw->id }})" class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                                <svg wire:loading wire:target="recheck({{ $kw->id }})" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"></circle><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path></svg>
                                            </button>
                                            <button wire:click="togglePause({{ $kw->id }})" title="{{ $kw->is_active ? 'Pause' : 'Resume' }}"
                                                class="rounded-md border border-slate-200 bg-white p-1.5 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                @if ($kw->is_active)
                                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" /></svg>
                                                @else
                                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347c-.75.412-1.667-.13-1.667-.986z" /></svg>
                                                @endif
                                            </button>
                                            <button wire:click="delete({{ $kw->id }})" wire:confirm="Delete this tracked keyword and its history?"
                                                class="rounded-md border border-slate-200 bg-white p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                                title="Delete">
                                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">{{ $rows->links() }}</div>
        @else
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-20 dark:border-slate-700 dark:bg-slate-900">
                <div class="rounded-full bg-indigo-50 p-3 dark:bg-indigo-500/10">
                    <svg class="h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                </div>
                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    @if ($search || $filterDevice || $filterCountry || $filterType || $filterStatus)
                        No keywords match your filters
                    @else
                        No keywords being tracked yet
                    @endif
                </p>
                <p class="mt-1 max-w-sm text-center text-xs text-slate-500 dark:text-slate-400">
                    @if ($search || $filterDevice || $filterCountry || $filterType || $filterStatus)
                        Try adjusting your filters or clear them to see everything.
                    @else
                        Add your first keyword to start monitoring its SERP position. Rankings are checked every 12 hours by default; you can force a re-check anytime.
                    @endif
                </p>
                <div class="mt-4 flex gap-2">
                    @if ($search || $filterDevice || $filterCountry || $filterType || $filterStatus)
                        <button wire:click="clearFilters" class="h-8 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Clear filters</button>
                    @endif
                    <button wire:click="toggleForm" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-indigo-600 px-3 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Add your first keyword
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
