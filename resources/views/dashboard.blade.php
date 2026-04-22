<x-layouts.app>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Overview of your website performance</p>
            </div>
            <livewire:dashboard.sync-and-report-panel />
        </div>
        <livewire:dashboard.kpi-cards />
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Action insights') }}</h2>
            <livewire:dashboard.country-filter />
        </div>
        <livewire:dashboard.insight-cards />
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <livewire:dashboard.traffic-chart />
            </div>
            <div class="lg:col-span-1">
                <livewire:dashboard.top-countries-card />
            </div>
        </div>
    </div>
</x-layouts.app>
