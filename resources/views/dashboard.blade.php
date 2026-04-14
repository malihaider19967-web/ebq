<x-layouts.app>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Overview of your website performance</p>
            </div>
        </div>
        <livewire:dashboard.kpi-cards />
        <livewire:dashboard.traffic-chart />
    </div>
</x-layouts.app>
