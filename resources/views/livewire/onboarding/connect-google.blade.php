<div class="space-y-6">
    {{-- Step indicator --}}
    <nav class="flex items-center justify-center gap-3 text-sm font-medium">
        <button wire:click="goToStep(1)"
            @class([
                'flex items-center gap-2 rounded-full px-4 py-2 transition',
                'bg-indigo-600 text-white shadow' => $step === 1,
                'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => $step !== 1,
            ])>
            @if ($googleConnected)
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            @else
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white/20 text-xs font-bold">1</span>
            @endif
            Google Account
        </button>

        <span class="h-px w-6 bg-slate-300 dark:bg-slate-600"></span>

        <button wire:click="goToStep(2)"
            @class([
                'flex items-center gap-2 rounded-full px-4 py-2 transition',
                'bg-indigo-600 text-white shadow' => $step === 2,
                'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => $step !== 2,
                'cursor-not-allowed opacity-50' => !$googleConnected && $step !== 2,
            ])>
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white/20 text-xs font-bold">2</span>
            Add Website
        </button>
    </nav>

    {{-- Card --}}
    <div class="rounded-lg bg-white p-6 shadow dark:bg-slate-900">

        {{-- Step 1: Connect Google --}}
        @if ($step === 1)
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/30">
                    <svg class="h-7 w-7 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-3.06a4.5 4.5 0 00-1.242-7.244l-4.5-4.5a4.5 4.5 0 00-6.364 6.364L4.34 8.374" />
                    </svg>
                </div>

                <h2 class="mb-1 text-lg font-semibold">Connect your Google account</h2>
                <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
                    We need read-only access to Google Analytics and Search Console to pull your website data.
                </p>

                @if ($googleConnected)
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Google account connected
                    </div>
                    <div>
                        <button wire:click="goToStep(2)"
                            class="rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Continue
                        </button>
                    </div>
                @else
                    <a href="{{ route('google.redirect') }}"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                        Connect with Google
                    </a>
                    <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">Read-only permissions. We never modify your data.</p>
                @endif
            </div>

        {{-- Step 2: Add Website --}}
        @else
            <div>
                <h2 class="mb-1 text-lg font-semibold">Add your website</h2>
                <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
                    Enter the domain and property IDs from your Google Analytics 4 and Search Console accounts.
                </p>

                <form wire:submit="saveWebsite" class="space-y-4">
                    <div>
                        <label for="domain" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Domain</label>
                        <input wire:model="domain" id="domain" type="text" placeholder="example.com"
                            class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                        @error('domain') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="gaPropertyId" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">GA4 Property ID</label>
                        <input wire:model="gaPropertyId" id="gaPropertyId" type="text" placeholder="properties/123456789"
                            class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Find this in GA4 &gt; Admin &gt; Property Settings.</p>
                        @error('gaPropertyId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="gscSiteUrl" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Search Console Site URL</label>
                        <input wire:model="gscSiteUrl" id="gscSiteUrl" type="text" placeholder="sc-domain:example.com"
                            class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Domain property (sc-domain:example.com) or URL prefix (https://example.com/).</p>
                        @error('gscSiteUrl') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <button type="button" wire:click="goToStep(1)"
                            class="text-sm font-medium text-slate-600 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200">
                            &larr; Back
                        </button>
                        <button type="submit"
                            class="rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Finish Setup
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
