<x-layouts.app>
    <div class="space-y-6">
        <div>
            <a href="{{ route('admin.research.competitor-scans.index') }}" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">&larr; Back to scans</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $scan->seed_domain }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $scan->seed_url }}</p>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">{{ session('status') }}</div>
        @endif

        <livewire:admin.competitor-scan-monitor :scan-id="$scan->id" />

        <livewire:admin.competitor-scan-results :scan-id="$scan->id" />
    </div>
</x-layouts.app>
