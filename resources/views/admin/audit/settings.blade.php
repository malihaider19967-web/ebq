<x-layouts.app>
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Page audits</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Controls optional third-party calls triggered by page audit runs and report views.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.audit.settings.update') }}"
            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-800/50">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="hidden" name="competitor_keywords_everywhere" value="0" />
                        <input type="checkbox" name="competitor_keywords_everywhere" value="1"
                            class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                            @checked(old('competitor_keywords_everywhere', $competitorKeywordsEverywhere)) />
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">
                                Fetch competitor data from Keywords Everywhere after audits
                            </span>
                            <span class="mt-1 block text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                                When enabled, a completed audit queues backlink samples for each competitor domain in the SERP benchmark.
                                Opening an audit report can also refresh stale domains. Each domain call spends Keywords Everywhere credits.
                                Disabled by default — manual <code class="rounded bg-white/80 px-1 py-px font-mono text-[10px] dark:bg-slate-900">ebq:fetch-competitor-backlinks</code> still works.
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit"
                    class="inline-flex h-9 items-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                    Save settings
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
