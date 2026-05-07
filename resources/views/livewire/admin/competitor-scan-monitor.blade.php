<div wire:poll.2s>
    @if ($scan === null)
        <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200">Scan not found.</div>
    @else
        @php
            $statusClasses = match ($scan->status) {
                'running' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200',
                'queued' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                'cancelling' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200',
                'done' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200',
                'failed' => 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
                default => 'bg-slate-100 text-slate-700',
            };
            $maxPages = $scan->caps['max_total_pages'] ?? null;
        @endphp

        <div class="space-y-4 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $statusClasses }}">{{ $scan->status }}</span>
                    <span class="text-xs text-slate-500">Triggered by {{ $scan->triggeredBy?->name ?? '—' }} {{ $scan->created_at?->diffForHumans() }}</span>
                </div>
                <div class="flex items-center gap-2">
                    @if ($scan->isActive())
                        <form method="POST" action="{{ route('admin.research.competitor-scans.cancel', $scan) }}" onsubmit="return confirm('Cancel this scan?');">
                            @csrf
                            <button type="submit" class="rounded-md border border-amber-200 px-2.5 py-1 text-[11px] font-semibold text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300 dark:hover:bg-amber-900/30">Cancel</button>
                        </form>
                    @endif
                    @if ($stale)
                        <form method="POST" action="{{ route('admin.research.competitor-scans.mark-failed', $scan) }}" onsubmit="return confirm('Force-mark this scan as failed?');">
                            @csrf
                            <button type="submit" class="rounded-md border border-rose-200 px-2.5 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-900/30">Mark failed</button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($stale)
                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">Heartbeat stale ({{ $scan->last_heartbeat_at?->diffForHumans() }}). Worker may have crashed.</div>
            @endif

            @if ($maxPages)
                <div>
                    <div class="flex items-center justify-between text-xs">
                        <span>{{ number_format($scan->page_count) }} / {{ number_format($maxPages) }} pages</span>
                        <span class="text-slate-500">{{ number_format(($progressFraction) * 100, 1) }}%</span>
                    </div>
                    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full bg-indigo-500" style="width: {{ (int) ($progressFraction * 100) }}%"></div>
                    </div>
                </div>
            @endif

            <dl class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                <div>
                    <dt class="text-slate-500">Pages</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format($scan->page_count) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">External pages</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format($scan->external_page_count) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Last heartbeat</dt>
                    <dd class="font-semibold">{{ $scan->last_heartbeat_at?->diffForHumans() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Started</dt>
                    <dd class="font-semibold">{{ $scan->started_at?->diffForHumans() ?? '—' }}</dd>
                </div>
            </dl>

            @if ($progressUrl)
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-800/60">
                    <span class="text-slate-500">Last URL:</span>
                    <span class="font-mono">{{ \Illuminate\Support\Str::limit($progressUrl, 120) }}</span>
                </div>
            @endif

            @if ($scan->seed_keywords)
                <div>
                    <h2 class="text-xs font-semibold uppercase text-slate-500">Seed keywords</h2>
                    <ul class="mt-1 flex flex-wrap gap-1.5 text-xs">
                        @foreach ($scan->seed_keywords as $seed)
                            <li class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{{ $seed }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($scan->error)
                <div class="rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200">
                    <div class="font-semibold">Error</div>
                    <pre class="mt-1 whitespace-pre-wrap font-mono text-[11px] leading-relaxed">{{ $scan->error }}</pre>
                </div>
            @endif
        </div>
    @endif
</div>
