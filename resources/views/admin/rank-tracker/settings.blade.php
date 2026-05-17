<x-layouts.app>
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Rank Tracker</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Platform defaults applied when customers add keywords on EBQ.io or via the WordPress plugin.
                SERP depth is fixed at {{ $defaultDepth }} results per check.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.rank-tracker.settings.update') }}"
            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label for="default_check_interval_hours" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Default re-check interval (hours)
                    </label>
                    <input type="number" name="default_check_interval_hours" id="default_check_interval_hours"
                        min="1" max="168" required
                        value="{{ old('default_check_interval_hours', $checkIntervalHours) }}"
                        class="h-9 w-full max-w-xs rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        How often active keywords are re-checked (1–168). Default is 72 hours (every 3 days).
                        Customers cannot override this in the product UI.
                    </p>
                    @error('default_check_interval_hours')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                    <strong class="text-slate-800 dark:text-slate-200">SERP depth:</strong> {{ $defaultDepth }} (not configurable)
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit"
                    class="inline-flex h-9 items-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                    Save defaults
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
