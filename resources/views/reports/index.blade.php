<x-layouts.app>
    <div class="space-y-6"
        x-data="{
            tab: new URL(location.href).searchParams.get('view') || 'insights',
            set(t) {
                this.tab = t;
                const url = new URL(location.href);
                url.searchParams.set('view', t);
                history.replaceState({}, '', url);
            }
        }">
        {{-- Page header --}}
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight">Reports</h1>
                    <a x-show="tab === 'insights'" href="{{ route('guide') }}#insights-panel" target="_blank" rel="noopener noreferrer" title="Open the matching section of the product guide in a new tab" class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 shadow-sm transition hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400">
                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
                        Guide
                    </a>
                    <a x-show="tab === 'email'" href="{{ route('guide') }}#growth-reports" target="_blank" rel="noopener noreferrer" title="Open the matching section of the product guide in a new tab" class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 shadow-sm transition hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400" x-cloak>
                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
                        Guide
                    </a>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Action-list insights and on-demand email reports for the selected website.</p>
            </div>

            {{-- Tab switcher --}}
            <div role="tablist" aria-label="Reports view" class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <button type="button" role="tab"
                    :aria-selected="tab === 'insights' ? 'true' : 'false'"
                    :class="tab === 'insights' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-100'"
                    @click="set('insights')"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition">
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                    Insights
                </button>
                <button type="button" role="tab"
                    :aria-selected="tab === 'email' ? 'true' : 'false'"
                    :class="tab === 'email' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-100'"
                    @click="set('email')"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition">
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                    Custom report
                </button>
            </div>
        </header>

        {{-- Tab panels --}}
        <div role="tabpanel" x-show="tab === 'insights'" x-transition.opacity.duration.150ms>
            <livewire:reports.insights-panel />
        </div>

        <div role="tabpanel" x-show="tab === 'email'" x-transition.opacity.duration.150ms x-cloak>
            <livewire:reports.report-generator />
        </div>
    </div>
</x-layouts.app>
