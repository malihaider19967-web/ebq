<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Custom page audit</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Audit any URL on the selected website and set the <strong class="text-slate-700 dark:text-slate-300">SERP benchmark keyword</strong> manually (Search Console primary query is not used for Serper on this run).
        </p>
    </div>

    @if ($message)
        <div @class([
            'rounded-lg border px-4 py-3 text-sm',
            'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-500/10 dark:text-emerald-200' => $messageKind === 'success',
            'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/40 dark:bg-rose-500/10 dark:text-rose-200' => $messageKind === 'error',
            'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/40 dark:bg-sky-500/10 dark:text-sky-200' => $messageKind === 'info',
        ]) role="status">
            {{ $message }}
        </div>
    @endif

    @if ($websiteId === 0)
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-500/10 dark:text-amber-200">
            Add or select a website in the header before running a custom audit.
        </p>
    @else
        <form wire:submit="runAudit" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div>
                <label for="custom-audit-url" class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Page URL</label>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Must be on the current website’s domain (https:// is added if you omit it).</p>
                <input
                    id="custom-audit-url"
                    type="url"
                    wire:model="pageUrl"
                    placeholder="https://example.com/your-page"
                    class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                    autocomplete="off"
                />
            </div>

            <div>
                <label for="custom-audit-keyword" class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Target keyword (SERP)</label>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Used for Serper organic results, rank-in-sample, and competitor readability / gap averages.</p>
                <input
                    id="custom-audit-keyword"
                    type="text"
                    wire:model="targetKeyword"
                    placeholder="e.g. best project management software"
                    class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                    autocomplete="off"
                />
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="runAudit"
                    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                >
                    <span wire:loading.remove wire:target="runAudit">Run audit</span>
                    <span wire:loading wire:target="runAudit">Running…</span>
                </button>
                <span wire:loading wire:target="runAudit" class="text-xs text-slate-500 dark:text-slate-400">This can take a minute.</span>
            </div>
        </form>
    @endif
</div>
