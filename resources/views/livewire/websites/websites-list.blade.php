<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Websites</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage your tracked websites</p>
        </div>
        <button wire:click="toggleForm"
            @class([
                'inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm transition',
                'bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600' => $showForm,
                'bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2' => !$showForm,
            ])>
            @if ($showForm)
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                Cancel
            @else
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add Website
            @endif
        </button>
    </div>

    @if ($showForm)
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-6 dark:border-indigo-500/20 dark:bg-indigo-500/5">
            <h3 class="mb-4 text-sm font-semibold text-slate-900 dark:text-slate-100">New Website</h3>

            @if ($fetchError)
                <div class="mb-4 flex items-center gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                    <svg class="h-4 w-4 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    {{ $fetchError }}
                </div>
            @endif

            <form wire:submit="addWebsite" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    {{-- GA4 Property --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">GA4 Property</label>
                        @if (count($gaProperties))
                            <select wire:model="gaPropertyId"
                                class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800">
                                <option value="">Select a property...</option>
                                @foreach ($gaProperties as $prop)
                                    <option value="{{ $prop['id'] }}">{{ $prop['name'] }} ({{ $prop['id'] }})</option>
                                @endforeach
                            </select>
                        @else
                            <input wire:model="gaPropertyId" type="text" placeholder="properties/123456789"
                                class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                            <p class="mt-1 text-xs text-slate-400">GA4 > Admin > Property Settings > Property ID</p>
                        @endif
                        @error('gaPropertyId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Search Console Site --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Search Console Site</label>
                        @if (count($gscSites))
                            <select wire:model.live="gscSiteUrl"
                                class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800">
                                <option value="">Select a site...</option>
                                @foreach ($gscSites as $site)
                                    <option value="{{ $site['siteUrl'] }}">{{ $site['siteUrl'] }}</option>
                                @endforeach
                            </select>
                        @else
                            <input wire:model.live="gscSiteUrl" type="text" placeholder="sc-domain:example.com"
                                class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                            <p class="mt-1 text-xs text-slate-400">Domain property (sc-domain:example.com) or URL prefix</p>
                        @endif
                        @error('gscSiteUrl') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Domain (auto-filled from GSC selection) --}}
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Domain</label>
                    <input wire:model="domain" type="text" placeholder="example.com"
                        class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 sm:max-w-xs" />
                    <p class="mt-1 text-xs text-slate-400">Auto-filled from Search Console selection.</p>
                    @error('domain') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        Save Website
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($websites->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($websites as $site)
                <div class="group rounded-xl border border-slate-200 bg-white p-5 transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-sm font-bold text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                                {{ strtoupper(substr($site->domain, 0, 1)) }}
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $site->domain }}</h3>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $site->gsc_site_url }}</p>
                            </div>
                        </div>
                        <button wire:click="removeWebsite({{ $site->id }})" wire:confirm="Remove this website? All synced data will be deleted."
                            class="rounded-lg p-1.5 text-slate-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 group-hover:opacity-100 dark:hover:bg-red-500/10 dark:hover:text-red-400">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                        </button>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                            GA4: {{ $site->ga_property_id ?: 'Not set' }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 dark:border-slate-700 dark:bg-slate-900">
            <svg class="h-12 w-12 text-slate-300 dark:text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" /></svg>
            <p class="mt-3 text-sm font-medium text-slate-500 dark:text-slate-400">No websites added yet</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Click "Add Website" to get started.</p>
        </div>
    @endif
</div>
