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
        <livewire:dashboard.traffic-chart />
    </div>
</x-layouts.app>
