<div class="flex w-full flex-col gap-3 sm:w-auto sm:items-end">
    @if ($website)
        <dl class="space-y-1 text-right text-xs text-slate-500 dark:text-slate-400">
            <div class="flex flex-wrap items-center justify-end gap-x-2 gap-y-0.5">
                <dt class="font-medium text-slate-600 dark:text-slate-300">Google Analytics</dt>
                <dd>{{ $website->last_analytics_sync_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-x-2 gap-y-0.5">
                <dt class="font-medium text-slate-600 dark:text-slate-300">Search Console</dt>
                <dd>{{ $website->last_search_console_sync_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-x-2 gap-y-0.5">
                <dt class="font-medium text-slate-600 dark:text-slate-300">Last report sent</dt>
                <dd>{{ $user?->last_growth_report_sent_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
        </dl>
    @else
        <p class="text-right text-xs text-slate-500 dark:text-slate-400">Select a website to see sync status.</p>
    @endif

    <div class="flex flex-col items-stretch gap-2 sm:items-end">
        <button type="button" wire:click="sendReport" wire:loading.attr="disabled"
            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-indigo-500 dark:hover:bg-indigo-400">
            <span wire:loading.remove wire:target="sendReport">Send report</span>
            <span wire:loading wire:target="sendReport">Sending…</span>
        </button>
        @if ($sendSuccess)
            <p class="text-right text-xs font-medium text-emerald-600 dark:text-emerald-400" role="status">{{ $sendSuccess }}</p>
        @endif
        @if ($sendError)
            <p class="text-right text-xs font-medium text-red-600 dark:text-red-400" role="alert">{{ $sendError }}</p>
        @endif
    </div>
</div>
