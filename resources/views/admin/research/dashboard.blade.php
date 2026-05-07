<x-layouts.app>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Research engine</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Live view of the continuous-research pipeline. Polls every 5 seconds.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.research.competitor-scans.create') }}" class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">New scan</a>
                <a href="{{ route('admin.research.competitor-scans.index') }}" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">All scans</a>
                <a href="{{ route('admin.research.niche-candidates.index') }}" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">Niche candidates</a>
                <a href="{{ route('admin.research.settings.show') }}" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">Settings</a>
            </div>
        </div>

        <livewire:admin.research-engine-dashboard />
    </div>
</x-layouts.app>
