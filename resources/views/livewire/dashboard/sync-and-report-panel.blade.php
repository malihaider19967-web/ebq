<div class="flex w-full flex-col gap-2.5 sm:w-auto">
    {{-- Buttons row --}}
    <div class="flex items-center justify-end gap-2">
        <a href="{{ route('reports.index') }}"
            class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" /></svg>
            Custom Reports
        </a>
        <button type="button" wire:click="sendReport" wire:loading.attr="disabled"
            class="inline-flex h-8 items-center gap-1.5 rounded-md bg-indigo-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-indigo-500 dark:hover:bg-indigo-400">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
            <span wire:loading.remove wire:target="sendReport">Send Daily Report</span>
            <span wire:loading wire:target="sendReport">Sending…</span>
        </button>
    </div>

    {{-- Sync timestamps --}}
    @if ($website)
        @php
            $gaAge = $website->last_analytics_sync_at?->diffInHours(now());
            $gscAge = $website->last_search_console_sync_at?->diffInHours(now());
        @endphp
        <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-0.5 text-[11px] text-slate-400 dark:text-slate-500">
            <span @class(['text-amber-600 dark:text-amber-400' => is_null($gaAge) || $gaAge > 24])>
                <span class="font-medium text-slate-500 dark:text-slate-400">GA</span>
                {{ $website->last_analytics_sync_at?->timezone(config('app.timezone'))->format('M j, g:i A') ?? '—' }}
                @if (is_null($gaAge) || $gaAge > 24)
                    <span class="font-semibold">(stale)</span>
                @endif
            </span>
            <span class="text-slate-300 dark:text-slate-700">|</span>
            <span @class(['text-amber-600 dark:text-amber-400' => is_null($gscAge) || $gscAge > 24])>
                <span class="font-medium text-slate-500 dark:text-slate-400">GSC</span>
                {{ $website->last_search_console_sync_at?->timezone(config('app.timezone'))->format('M j, g:i A') ?? '—' }}
                @if (is_null($gscAge) || $gscAge > 24)
                    <span class="font-semibold">(stale)</span>
                @endif
            </span>
            <span class="text-slate-300 dark:text-slate-700">|</span>
            <span><span class="font-medium text-slate-500 dark:text-slate-400">Report</span> {{ $user?->last_growth_report_sent_at?->timezone(config('app.timezone'))->format('M j, g:i A') ?? '—' }}</span>
        </div>
    @endif

    @if ($sendSuccess)
        <p class="text-right text-xs font-medium text-emerald-600 dark:text-emerald-400" role="status">{{ $sendSuccess }}</p>
    @endif
    @if ($sendError)
        <p class="text-right text-xs font-medium text-red-600 dark:text-red-400" role="alert">{{ $sendError }}</p>
    @endif
</div>
