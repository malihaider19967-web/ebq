<x-layouts.app>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Keyword Gap Analysis</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">See the keywords your competitors target that you don’t — bucketed into gaps to win, pages to strengthen, and your strengths.</p>
            </div>
            <a href="{{ route('competitive.competitors') }}" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">Find competitors →</a>
        </div>
        <livewire:competitive.keyword-gap-analysis />
    </div>
</x-layouts.app>
