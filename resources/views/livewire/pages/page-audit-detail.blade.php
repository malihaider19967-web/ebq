<div class="mx-auto max-w-5xl space-y-5 px-4 py-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('pages.index') }}" class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 transition hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400" wire:navigate>
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                Pages
            </a>
            <h1 class="mt-2 text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Page audit</h1>
            <p class="mt-1 break-all font-mono text-sm text-slate-600 dark:text-slate-300">{{ $pageAuditReport->page }}</p>
            @php
                $hdrPl = is_array($pageAuditReport->result ?? null) ? ($pageAuditReport->result['page_locale'] ?? null) : null;
                $hdrPlLabel = \App\Support\Audit\PageLocalePresentation::shortLabel(is_array($hdrPl) ? $hdrPl : null);
            @endphp
            @if ($hdrPlLabel)
                <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                    <span class="font-semibold text-slate-800 dark:text-slate-100">Detected market</span>
                    {{ $hdrPlLabel }}
                    @if (! empty($hdrPl['source'] ?? null))
                        <span class="text-slate-400 dark:text-slate-500">· {{ str_replace('_', ' ', (string) $hdrPl['source']) }}</span>
                    @endif
                </p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('pages.show', ['id' => rawurlencode($pageAuditReport->page)]) }}"
                wire:navigate
                class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                Page context
            </a>
            <a
                href="{{ $pageAuditReport->page }}"
                target="_blank"
                rel="noopener"
                class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                Open URL
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
            </a>
        </div>
    </header>

    @if ($auditMessage)
        <div @class([
            'rounded-lg border px-4 py-3 text-sm',
            'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-500/10 dark:text-emerald-200' => $auditMessageKind === 'success',
            'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/40 dark:bg-rose-500/10 dark:text-rose-200' => $auditMessageKind === 'error',
            'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/40 dark:bg-sky-500/10 dark:text-sky-200' => $auditMessageKind === 'info',
        ]) role="status">
            {{ $auditMessage }}
        </div>
    @endif

    @php
        $trOnPage = $trackedRankings['on_this_page'] ?? [];
        $trOnSite = $trackedRankings['on_site'] ?? [];
        $hasTracked = ! empty($trOnPage) || ! empty($trOnSite);
    @endphp

    @if ($hasTracked)
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3 dark:border-slate-800">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-5 items-center gap-1 rounded-full bg-indigo-600 px-2 text-[10px] font-semibold uppercase tracking-wider text-white">Rank tracker</span>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Tracked keywords ranking for this page</h2>
                    </div>
                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">Matches against the latest SERP check for each keyword you're tracking.</p>
                </div>
                <a href="{{ route('rank-tracking.index') }}" wire:navigate class="text-[11px] font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Manage →</a>
            </div>

            @if (! empty($trOnPage))
                <div class="px-5 pt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Ranked for this exact URL</div>
                        <span class="text-[10px] text-slate-400">{{ count($trOnPage) }}</span>
                    </div>
                    <ul class="divide-y divide-slate-100 rounded-md border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                        @foreach ($trOnPage as $row)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-3 py-2 text-xs">
                                <div class="min-w-0">
                                    <a href="{{ route('rank-tracking.show', $row['id']) }}" wire:navigate class="font-semibold text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-indigo-400">{{ $row['keyword'] }}</a>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                        <span class="rounded bg-slate-100 px-1.5 py-px text-[9px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $row['search_type'] }}</span>
                                        <span class="rounded bg-slate-100 px-1.5 py-px text-[9px] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $row['country'] }}</span>
                                        <span class="rounded bg-slate-100 px-1.5 py-px text-[9px] uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $row['device'] }}</span>
                                        @if (! $row['is_active'])<span class="rounded bg-slate-200 px-1.5 py-px text-[9px] font-semibold uppercase text-slate-600 dark:bg-slate-700 dark:text-slate-300">Paused</span>@endif
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2 tabular-nums">
                                    @if ($row['change'] > 0)
                                        <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">▲{{ $row['change'] }}</span>
                                    @elseif ($row['change'] < 0)
                                        <span class="text-[10px] font-semibold text-red-600 dark:text-red-400">▼{{ abs($row['change']) }}</span>
                                    @endif
                                    <span @class([
                                        'inline-flex min-w-[42px] justify-center rounded-full px-2 py-0.5 text-[11px] font-bold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' => $row['position'] <= 3,
                                        'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400' => $row['position'] > 3 && $row['position'] <= 10,
                                        'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' => $row['position'] > 10 && $row['position'] <= 20,
                                        'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => $row['position'] > 20,
                                    ])>#{{ $row['position'] }}</span>
                                    <span class="text-[10px] text-slate-400">best #{{ $row['best'] ?? '—' }}</span>
                                    @if (! empty($row['last_checked_at']))
                                        <span class="text-[10px] text-slate-400">· {{ $row['last_checked_at']->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="px-5 pt-4">
                    <div class="rounded-md border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-500 dark:border-slate-700 dark:bg-slate-800/40">
                        No tracked keywords are currently ranking to this exact URL. See other ranked pages on this site below.
                    </div>
                </div>
            @endif

            @if (! empty($trOnSite))
                <div class="px-5 pb-4 pt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Other ranked pages on this site</div>
                        <span class="text-[10px] text-slate-400">{{ count($trOnSite) }}</span>
                    </div>
                    <ul class="divide-y divide-slate-100 rounded-md border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                        @foreach (array_slice($trOnSite, 0, 25) as $row)
                            <li class="flex flex-wrap items-start justify-between gap-3 px-3 py-2 text-xs">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('rank-tracking.show', $row['id']) }}" wire:navigate class="font-semibold text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-indigo-400">{{ $row['keyword'] }}</a>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                        <span class="rounded bg-slate-100 px-1.5 py-px text-[9px] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $row['country'] }}</span>
                                        <span class="rounded bg-slate-100 px-1.5 py-px text-[9px] uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $row['device'] }}</span>
                                    </div>
                                    @if (! empty($row['url']))
                                        <a href="{{ $row['url'] }}" target="_blank" rel="noopener" class="mt-0.5 block truncate text-[10px] text-emerald-700 hover:underline dark:text-emerald-400">{{ $row['url'] }}</a>
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center gap-2 tabular-nums">
                                    @if ($row['change'] > 0)
                                        <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">▲{{ $row['change'] }}</span>
                                    @elseif ($row['change'] < 0)
                                        <span class="text-[10px] font-semibold text-red-600 dark:text-red-400">▼{{ abs($row['change']) }}</span>
                                    @endif
                                    <span @class([
                                        'inline-flex min-w-[42px] justify-center rounded-full px-2 py-0.5 text-[11px] font-bold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' => $row['position'] <= 3,
                                        'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400' => $row['position'] > 3 && $row['position'] <= 10,
                                        'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' => $row['position'] > 10 && $row['position'] <= 20,
                                        'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => $row['position'] > 20,
                                    ])>#{{ $row['position'] }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @if (count($trOnSite) > 25)
                        <p class="mt-2 text-[10px] text-slate-400">Showing 25 of {{ count($trOnSite) }} · open Rank Tracking for the full list.</p>
                    @endif
                </div>
            @endif
        </section>
    @else
        <section class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-4 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-5 items-center gap-1 rounded-full bg-slate-200 px-2 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-700 dark:text-slate-300">Rank tracker</span>
                    <span>No tracked keywords match this site yet. Add keywords to monitor rankings for <span class="font-semibold text-slate-700 dark:text-slate-300">{{ parse_url($pageAuditReport->page, PHP_URL_HOST) }}</span>.</span>
                </div>
                <a href="{{ route('rank-tracking.index') }}" wire:navigate class="shrink-0 rounded-md bg-indigo-600 px-3 py-1 text-[11px] font-semibold text-white hover:bg-indigo-700">Open Rank Tracking →</a>
            </div>
        </section>
    @endif

    @include('livewire.pages.partials.audit-report', ['auditReport' => $pageAuditReport, 'openAuditSummary' => true])
</div>
