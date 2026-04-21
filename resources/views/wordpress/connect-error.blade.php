<x-layouts.app>
    <div class="mx-auto max-w-xl space-y-4">
        <h1 class="text-xl font-bold tracking-tight">Can't connect</h1>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-300">
            {{ $message }}
        </div>
        <a href="{{ route('dashboard') }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Back to dashboard</a>
    </div>
</x-layouts.app>
