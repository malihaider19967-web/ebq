<div>
    @if (session('rank_tracking_status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-medium text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('rank_tracking_status') }}
        </div>
    @endif

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 items-center gap-2">
            <div class="relative flex-1 sm:max-w-xs">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search tracked keywords…"
                    class="h-8 w-full rounded-md border border-slate-200 bg-white pl-8 pr-2.5 text-xs placeholder-slate-400 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:placeholder-slate-500" />
            </div>
        </div>
        <button wire:click="toggleForm" type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-md bg-indigo-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            {{ $showForm ? 'Close' : 'Add keyword' }}
        </button>
    </div>

    @if ($showForm)
        <form wire:submit.prevent="addKeyword"
            class="mb-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="mb-4 text-sm font-semibold text-slate-900 dark:text-slate-100">Track a new keyword</h3>

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
                    <p class="mt-1 text-[11px] text-slate-400">Leave empty to match any URL on the domain.</p>
                </div>

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
                    <p class="mt-1 text-[11px] text-slate-400">City-level SERP targeting. Leave blank for country-level.</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Device</label>
                    <select wire:model="newDevice" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <option value="desktop">Desktop</option>
                        <option value="mobile">Mobile</option>
                    </select>
                </div>

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
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Time filter (tbs)</label>
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
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Check interval (hours)</label>
                    <input wire:model="newIntervalHours" type="number" min="1" max="168"
                        class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                    <p class="mt-1 text-[11px] text-slate-400">Default 12h. Force re-check is always available.</p>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Competitor domains (comma-separated)</label>
                    <input wire:model="newCompetitors" type="text" placeholder="competitor1.com, competitor2.com"
                        class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Tags / group (comma-separated)</label>
                    <input wire:model="newTags" type="text" placeholder="homepage, brand, priority"
                        class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                    <textarea wire:model="newNotes" rows="2"
                        class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800"></textarea>
                </div>

                <div class="md:col-span-2 flex flex-wrap items-center gap-5 pt-1">
                    <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                        <input wire:model="newAutocorrect" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                        Autocorrect queries
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                        <input wire:model="newSafeSearch" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                        Safe search
                    </label>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" wire:click="toggleForm"
                    class="h-8 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Cancel</button>
                <button type="submit"
                    class="h-8 rounded-md bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">
                    Add & run first check
                </button>
            </div>
        </form>
    @endif

    @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                            <th class="px-4 py-2.5 text-left"><button wire:click="sort('keyword')" class="uppercase tracking-wider">Keyword</button></th>
                            <th class="px-4 py-2.5 text-left uppercase tracking-wider">Target</th>
                            <th class="px-4 py-2.5 text-left uppercase tracking-wider">Location</th>
                            <th class="px-4 py-2.5 text-left uppercase tracking-wider">Device</th>
                            <th class="px-4 py-2.5 text-right"><button wire:click="sort('current_position')" class="uppercase tracking-wider">Rank</button></th>
                            <th class="px-4 py-2.5 text-right"><button wire:click="sort('position_change')" class="uppercase tracking-wider">Δ</button></th>
                            <th class="px-4 py-2.5 text-right"><button wire:click="sort('best_position')" class="uppercase tracking-wider">Best</button></th>
                            <th class="px-4 py-2.5 text-left"><button wire:click="sort('last_checked_at')" class="uppercase tracking-wider">Last check</button></th>
                            <th class="px-4 py-2.5 text-right uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($rows as $kw)
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('rank-tracking.show', $kw->id) }}" class="font-medium text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-indigo-400">{{ $kw->keyword }}</a>
                                    <div class="text-[10px] uppercase tracking-wide text-slate-400">{{ $kw->search_type }}</div>
                                    @if ($kw->tags)
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach ((array) $kw->tags as $tag)
                                                <span class="rounded bg-slate-100 px-1.5 py-px text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $tag }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">
                                    <div>{{ $kw->target_domain }}</div>
                                    @if ($kw->current_url)
                                        <a href="{{ $kw->current_url }}" target="_blank" rel="noopener" class="text-[10px] text-indigo-600 hover:underline dark:text-indigo-400">{{ \Illuminate\Support\Str::limit($kw->current_url, 60) }}</a>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400">
                                    <div>{{ strtoupper($kw->country) }} · {{ $kw->language }}</div>
                                    @if ($kw->location)<div class="text-[10px] text-slate-400">{{ $kw->location }}</div>@endif
                                </td>
                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400">{{ ucfirst($kw->device) }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    @if ($kw->current_position)
                                        <span @class([
                                            'inline-flex rounded-full px-2 py-px text-[11px] font-semibold tabular-nums',
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $kw->current_position <= 3,
                                            'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' => $kw->current_position > 3 && $kw->current_position <= 10,
                                            'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' => $kw->current_position > 10 && $kw->current_position <= 20,
                                            'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => $kw->current_position > 20,
                                        ])>#{{ $kw->current_position }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    @if ($kw->position_change > 0)
                                        <span class="text-emerald-600 dark:text-emerald-400">▲ {{ $kw->position_change }}</span>
                                    @elseif ($kw->position_change < 0)
                                        <span class="text-red-600 dark:text-red-400">▼ {{ abs($kw->position_change) }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ $kw->best_position ? '#'.$kw->best_position : '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400">
                                    @if ($kw->last_checked_at)
                                        <div>{{ $kw->last_checked_at->diffForHumans() }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $kw->last_status }}</div>
                                    @else
                                        <span class="text-slate-400">pending</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button wire:click="recheck({{ $kw->id }})" title="Force re-check"
                                            class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-indigo-600 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                                            Recheck
                                        </button>
                                        <button wire:click="togglePause({{ $kw->id }})" title="{{ $kw->is_active ? 'Pause' : 'Resume' }}"
                                            class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            {{ $kw->is_active ? 'Pause' : 'Resume' }}
                                        </button>
                                        <button wire:click="delete({{ $kw->id }})" wire:confirm="Delete this tracked keyword and its history?" title="Delete"
                                            class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-red-600 hover:bg-red-50 dark:border-slate-700 dark:bg-slate-800 dark:text-red-400 dark:hover:bg-red-500/10">
                                            Delete
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
        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-16 dark:border-slate-800 dark:bg-slate-900">
            <svg class="h-12 w-12 text-slate-300 dark:text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
            <p class="mt-3 text-sm font-medium text-slate-500 dark:text-slate-400">No keywords being tracked yet</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Add a keyword to start monitoring its SERP position.</p>
        </div>
    @endif
</div>
