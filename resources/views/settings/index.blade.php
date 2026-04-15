<x-layouts.app>
    <div class="space-y-6" x-data="{ tab: 'account' }">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Settings</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage your account, integrations, and report preferences</p>
        </div>

        {{-- Tabs --}}
        <div class="border-b border-slate-200 dark:border-slate-800">
            <nav class="-mb-px flex gap-6" aria-label="Settings tabs">
                <button type="button" @click="tab = 'account'"
                    :class="tab === 'account'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                    class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium transition">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                    Account
                </button>
                <button type="button" @click="tab = 'integrations'"
                    :class="tab === 'integrations'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                    class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium transition">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
                    Integrations
                </button>
                <button type="button" @click="tab = 'reports'"
                    :class="tab === 'reports'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                    class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium transition">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" /></svg>
                    Reports
                </button>
            </nav>
        </div>

        {{-- Tab panels --}}
        <div class="mx-auto max-w-2xl">
            <div x-show="tab === 'account'" x-cloak>
                <livewire:settings.profile-settings />
            </div>

            <div x-show="tab === 'integrations'" x-cloak>
                <livewire:settings.integrations-panel />
            </div>

            <div x-show="tab === 'reports'" x-cloak>
                <livewire:settings.report-recipients />
            </div>
        </div>
    </div>
</x-layouts.app>
