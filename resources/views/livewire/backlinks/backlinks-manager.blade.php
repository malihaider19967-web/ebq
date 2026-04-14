<div>
    @if (! $websiteId || ! Auth::user()->websites()->whereKey($websiteId)->exists())
        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-16 dark:border-slate-800 dark:bg-slate-900">
            <svg class="h-12 w-12 text-slate-300 dark:text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3" /></svg>
            <p class="mt-3 text-sm font-medium text-slate-500 dark:text-slate-400">Select a website from the header</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Add a website under Websites if you have not yet.</p>
        </div>
    @else
        <div class="space-y-8">
            {{-- Add single --}}
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:p-6">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Add backlink</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Saved for the selected website and the date you choose.</p>
                <form wire:submit="addBacklink" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Tracked date</label>
                        <input wire:model="tracked_date" type="date" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                        @error('tracked_date') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2 lg:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Referring page URL</label>
                        <input wire:model="referring_page_url" type="url" placeholder="https://…" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                        @error('referring_page_url') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Target page URL (your site)</label>
                        <input wire:model="target_page_url" type="url" placeholder="https://…" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                        @error('target_page_url') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Domain authority</label>
                        <input wire:model="domain_authority" type="number" min="0" max="100" placeholder="—" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                        @error('domain_authority') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Spam score</label>
                        <input wire:model="spam_score" type="number" min="0" max="100" placeholder="—" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                        @error('spam_score') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Type</label>
                        <select wire:model="type" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800">
                            @foreach ($types as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Anchor text</label>
                        <input wire:model="anchor_text" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                        @error('anchor_text') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end gap-3">
                        <label class="flex cursor-pointer items-center gap-2 pb-2 text-sm text-slate-700 dark:text-slate-300">
                            <input wire:model="is_dofollow" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                            Dofollow
                        </label>
                    </div>
                    <div class="flex items-end sm:col-span-2 lg:col-span-3">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            Save backlink
                        </button>
                    </div>
                </form>
            </div>

            {{-- Bulk sheet --}}
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Bulk edit by date</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Open a sheet for one tracked date to add or update many rows at once.</p>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Sheet date</label>
                            <input wire:model.live="sheetDate" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                            @error('sheetDate') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        @if ($sheetOpen)
                            <button type="button" wire:click="closeSheet" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Close sheet</button>
                        @else
                            <button type="button" wire:click="openSheet" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Open sheet</button>
                        @endif
                    </div>
                </div>

                @if ($sheetOpen)
                    <div class="mt-4 space-y-3">
                        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                            <table class="min-w-[900px] w-full text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50 text-left font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                                        <th class="px-2 py-2">Referring URL</th>
                                        <th class="px-2 py-2">Target URL</th>
                                        <th class="px-2 py-2 w-16">DA</th>
                                        <th class="px-2 py-2 w-16">Spam</th>
                                        <th class="px-2 py-2">Anchor</th>
                                        <th class="px-2 py-2">Type</th>
                                        <th class="px-2 py-2">Follow</th>
                                        <th class="px-2 py-2 w-20"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($sheetRows as $i => $row)
                                        <tr wire:key="sheet-row-{{ $i }}" class="align-top">
                                            <td class="p-1">
                                                <input wire:model.blur="sheetRows.{{ $i }}.referring_page_url" type="url" class="w-full min-w-[200px] rounded border border-slate-200 bg-white px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-800" />
                                            </td>
                                            <td class="p-1">
                                                <input wire:model.blur="sheetRows.{{ $i }}.target_page_url" type="url" class="w-full min-w-[200px] rounded border border-slate-200 bg-white px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-800" />
                                            </td>
                                            <td class="p-1">
                                                <input wire:model.blur="sheetRows.{{ $i }}.domain_authority" type="number" min="0" max="100" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-800" />
                                            </td>
                                            <td class="p-1">
                                                <input wire:model.blur="sheetRows.{{ $i }}.spam_score" type="number" min="0" max="100" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-800" />
                                            </td>
                                            <td class="p-1">
                                                <input wire:model.blur="sheetRows.{{ $i }}.anchor_text" type="text" class="w-full min-w-[120px] rounded border border-slate-200 bg-white px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-800" />
                                            </td>
                                            <td class="p-1">
                                                <select wire:model.live="sheetRows.{{ $i }}.type" class="w-full min-w-[140px] rounded border border-slate-200 bg-white px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-800">
                                                    @foreach ($types as $t)
                                                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="p-1">
                                                <select wire:model.live="sheetRows.{{ $i }}.is_dofollow" class="w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-800">
                                                    <option value="1">Dofollow</option>
                                                    <option value="0">Nofollow</option>
                                                </select>
                                            </td>
                                            <td class="p-1 whitespace-nowrap">
                                                <button type="button" wire:click="removeSheetRow({{ $i }})" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Remove</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @foreach ($sheetRows as $i => $row)
                            @if ($errors->has("sheetRows.$i.referring_page_url") || $errors->has("sheetRows.$i.target_page_url"))
                                <div class="text-xs text-red-600 dark:text-red-400">
                                    Row {{ $i + 1 }}: {{ $errors->first("sheetRows.$i.referring_page_url") ?: $errors->first("sheetRows.$i.target_page_url") }}
                                </div>
                            @endif
                        @endforeach
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="addSheetRow" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Add row</button>
                            <button type="button" wire:click="saveSheet" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Save sheet</button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Filters + table --}}
            <div>
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between">
                    <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="relative flex-1 sm:max-w-xs">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search URLs or anchor…"
                                class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm placeholder-slate-400 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:placeholder-slate-500" />
                        </div>
                        <input wire:model.live="from" type="date" title="From" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        <input wire:model.live="to" type="date" title="To" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        <select wire:model.live="typeFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
                            <option value="">All types</option>
                            @foreach ($types as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="followFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
                            <option value="">All links</option>
                            <option value="dofollow">Dofollow</option>
                            <option value="nofollow">Nofollow</option>
                        </select>
                        <input wire:model.live="daMin" type="number" min="0" max="100" placeholder="DA min" class="w-24 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        <input wire:model.live="daMax" type="number" min="0" max="100" placeholder="DA max" class="w-24 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        <input wire:model.live="spamMin" type="number" min="0" max="100" placeholder="Spam min" class="w-28 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        <input wire:model.live="spamMax" type="number" min="0" max="100" placeholder="Spam max" class="w-28 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                    </div>
                </div>

                @if ($rows->isNotEmpty())
                    <div class="max-w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="max-w-full overflow-x-auto">
                            <table class="w-full min-w-0 table-fixed text-sm">
                                <colgroup>
                                    <col class="w-[6.5rem]" />
                                    <col />
                                    <col />
                                    <col class="w-12" />
                                    <col class="w-12" />
                                    <col class="w-[9rem]" />
                                    <col class="w-[7.5rem]" />
                                    <col class="w-[6.25rem]" />
                                    <col class="w-[6.5rem]" />
                                </colgroup>
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                                        <x-sort-header column="tracked_date" :sortBy="$sortBy" :sortDir="$sortDir" th-class="min-w-0 px-2 py-3">Date</x-sort-header>
                                        <x-sort-header column="referring_page_url" :sortBy="$sortBy" :sortDir="$sortDir" th-class="min-w-0 px-2 py-3">Referring</x-sort-header>
                                        <x-sort-header column="target_page_url" :sortBy="$sortBy" :sortDir="$sortDir" th-class="min-w-0 px-2 py-3">Target</x-sort-header>
                                        <x-sort-header column="domain_authority" :sortBy="$sortBy" :sortDir="$sortDir" align="right" th-class="px-2 py-3">DA</x-sort-header>
                                        <x-sort-header column="spam_score" :sortBy="$sortBy" :sortDir="$sortDir" align="right" th-class="px-2 py-3">Spam</x-sort-header>
                                        <x-sort-header column="anchor_text" :sortBy="$sortBy" :sortDir="$sortDir" th-class="min-w-0 px-2 py-3">Anchor</x-sort-header>
                                        <x-sort-header column="type" :sortBy="$sortBy" :sortDir="$sortDir" th-class="min-w-0 px-2 py-3">Type</x-sort-header>
                                        <x-sort-header column="is_dofollow" :sortBy="$sortBy" :sortDir="$sortDir" th-class="px-2 py-3">Follow</x-sort-header>
                                        <th class="w-[6.5rem] px-2 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($rows as $b)
                                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                            <td class="whitespace-nowrap px-2 py-2.5 align-top text-slate-600 dark:text-slate-300">{{ $b->tracked_date->format('M j, Y') }}</td>
                                            <td class="min-w-0 px-2 py-2.5 align-top">
                                                <a href="{{ $b->referring_page_url }}" target="_blank" rel="noopener noreferrer" title="{{ $b->referring_page_url }}" class="block min-w-0 truncate font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $b->referring_page_url }}</a>
                                            </td>
                                            <td class="min-w-0 px-2 py-2.5 align-top">
                                                <a href="{{ $b->target_page_url }}" target="_blank" rel="noopener noreferrer" title="{{ $b->target_page_url }}" class="block min-w-0 truncate text-indigo-600 hover:underline dark:text-indigo-400">{{ $b->target_page_url }}</a>
                                            </td>
                                            <td class="whitespace-nowrap px-2 py-2.5 text-right align-top tabular-nums text-slate-700 dark:text-slate-300">{{ $b->domain_authority ?? '—' }}</td>
                                            <td class="whitespace-nowrap px-2 py-2.5 text-right align-top tabular-nums text-slate-700 dark:text-slate-300">{{ $b->spam_score ?? '—' }}</td>
                                            <td class="min-w-0 truncate px-2 py-2.5 align-top text-slate-700 dark:text-slate-300" title="{{ $b->anchor_text }}">{{ $b->anchor_text ?? '—' }}</td>
                                            <td class="min-w-0 truncate px-2 py-2.5 align-top text-slate-700 dark:text-slate-300" title="{{ $b->type->label() }}">{{ $b->type->label() }}</td>
                                            <td class="whitespace-nowrap px-2 py-2.5 align-top">
                                                <span @class([
                                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $b->is_dofollow,
                                                    'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => ! $b->is_dofollow,
                                                ])>{{ $b->is_dofollow ? 'Dofollow' : 'Nofollow' }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-2 py-2.5 text-right align-top text-xs leading-tight">
                                                <button type="button" wire:click="openSheetForDate('{{ $b->tracked_date->format('Y-m-d') }}')" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Sheet</button>
                                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                                <button type="button" wire:click="deleteBacklink({{ $b->id }})" wire:confirm="Delete this backlink?" class="font-medium text-red-600 hover:underline dark:text-red-400">Delete</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mt-4">{{ $rows->links() }}</div>
                @else
                    <div class="flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-16 dark:border-slate-800 dark:bg-slate-900">
                        <svg class="h-12 w-12 text-slate-300 dark:text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 011.242 7.244l-4.5 4.5a4.5 4.5 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 00-6.364-6.364l-4.5 4.5a4.5 4.5 001.242 7.244" /></svg>
                        <p class="mt-3 text-sm font-medium text-slate-500 dark:text-slate-400">No backlinks yet</p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Use the form above or open a sheet for a date to add entries.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
