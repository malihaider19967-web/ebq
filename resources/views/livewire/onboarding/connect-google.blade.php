<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Get started</h1>
        <p class="mt-2 text-sm text-slate-600">Connect your Google account and add your first website.</p>
    </div>

    {{-- Step indicator --}}
    <div class="flex items-center gap-3">
        <button wire:click="goToStep(1)" class="flex items-center gap-2">
            <span @class([
                'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition',
                'bg-indigo-600 text-white' => $step === 1 && !$googleConnected,
                'bg-emerald-500 text-white' => $googleConnected,
                'bg-slate-200 text-slate-600' => $step !== 1 && !$googleConnected,
            ])>
                @if ($googleConnected)
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                @else
                    1
                @endif
            </span>
            <span class="text-sm font-medium {{ $step === 1 ? 'text-slate-900' : 'text-slate-500' }}">Connect</span>
        </button>

        <div class="h-px flex-1 bg-slate-200"></div>

        <button wire:click="goToStep(2)" @disabled(!$googleConnected && $step !== 2) class="flex items-center gap-2">
            <span @class([
                'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition',
                'bg-indigo-600 text-white' => $step === 2,
                'bg-slate-200 text-slate-600' => $step !== 2,
            ])>2</span>
            <span class="text-sm font-medium {{ $step === 2 ? 'text-slate-900' : 'text-slate-500' }}">Add Site</span>
        </button>
    </div>

    {{-- Step 1: Connect Google --}}
    @if ($step === 1)
        <div class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50">
                <svg class="h-7 w-7 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-3.06a4.5 4.5 0 00-1.242-7.244l-4.5-4.5a4.5 4.5 0 00-6.364 6.364L4.34 8.374" /></svg>
            </div>

            <h2 class="text-lg font-bold text-slate-900">Connect your Google account</h2>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                We need read-only access to Google Analytics and Search Console to pull your website data.
            </p>

            @if ($googleConnected)
                <div class="mt-6 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Google account connected
                </div>
                <div class="mt-4">
                    <button wire:click="goToStep(2)"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        Continue
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                    </button>
                </div>
            @else
                <div class="mt-6">
                    <a href="{{ route('google.redirect') }}"
                        class="inline-flex items-center gap-2.5 rounded-lg bg-slate-900 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                        Sign in with Google
                    </a>
                </div>
                <p class="mt-4 text-xs text-slate-400">Read-only permissions. We never modify your data.</p>
            @endif
        </div>

    {{-- Step 2: Add Website --}}
    @else
        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Select your website</h2>
            <p class="mt-1 text-sm text-slate-500">Choose a GA4 property and Search Console site.</p>

            @if ($fetchError)
                <div class="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <svg class="h-4 w-4 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    {{ $fetchError }}
                </div>
            @endif

            <form wire:submit="saveWebsite" class="mt-6 space-y-5">
                <div>
                    <label for="gaPropertyId" class="mb-1.5 block text-xs font-medium text-slate-700">GA4 Property</label>
                    @if (count($gaProperties))
                        <select wire:model="gaPropertyId" id="gaPropertyId"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                            <option value="">Select a property...</option>
                            @foreach ($gaProperties as $prop)
                                <option value="{{ $prop['id'] }}">{{ $prop['name'] }} ({{ $prop['id'] }})</option>
                            @endforeach
                        </select>
                    @else
                        <input wire:model="gaPropertyId" id="gaPropertyId" type="text" placeholder="properties/123456789"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
                        <p class="mt-1 text-xs text-slate-400">GA4 > Admin > Property Settings > Property ID</p>
                    @endif
                    @error('gaPropertyId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="gscSiteUrl" class="mb-1.5 block text-xs font-medium text-slate-700">Search Console Site</label>
                    @if (count($gscSites))
                        <select wire:model.live="gscSiteUrl" id="gscSiteUrl"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                            <option value="">Select a site...</option>
                            @foreach ($gscSites as $site)
                                <option value="{{ $site['siteUrl'] }}">{{ $site['siteUrl'] }}</option>
                            @endforeach
                        </select>
                    @else
                        <input wire:model.live="gscSiteUrl" id="gscSiteUrl" type="text" placeholder="sc-domain:example.com"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
                        <p class="mt-1 text-xs text-slate-400">Domain property (sc-domain:example.com) or URL prefix (https://example.com/)</p>
                    @endif
                    @error('gscSiteUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="domain" class="mb-1.5 block text-xs font-medium text-slate-700">Domain</label>
                    <input wire:model="domain" id="domain" type="text" placeholder="example.com"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
                    <p class="mt-1 text-xs text-slate-400">Auto-filled from Search Console selection.</p>
                    @error('domain') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-between border-t border-slate-100 pt-5">
                    <button type="button" wire:click="goToStep(1)"
                        class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 transition hover:text-slate-700">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                        Back
                    </button>
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        Finish Setup
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
