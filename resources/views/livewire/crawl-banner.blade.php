<div wire:poll.10s>
    @if ($crawl)
        <div class="flex items-start gap-3 rounded-xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-500/30 dark:bg-indigo-500/10">
            <div class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">
                {{-- Spinner: signals work in progress --}}
                <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
            @php
                $finalizing = $crawl->status === \App\Models\CrawlRun::STATUS_FINALIZING;
                // Cap-relative progress: how far through THIS user's allowance the
                // shared crawl is. Bounded by the cap so a small-plan user shows
                // "1,000 / 1,000" (their window is full) while the crawl keeps going.
                $seen = $cap > 0 ? min((int) $crawl->pages_seen, $cap) : (int) $crawl->pages_seen;
            @endphp
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                    @if ($finalizing) We’re computing your results @else We’re crawling your website right now @endif
                </h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    @if ($finalizing)
                        The crawl finished — we’re scoring your pages and building Site Health,
                        page-level issues and SEO scores. This page fills in automatically when it’s done.
                    @else
                        We’re fetching and analysing your pages to build Site Health, page-level
                        issues and SEO scores. This usually takes a few minutes — the dashboard
                        will fill in automatically as the crawl progresses.
                    @endif
                </p>
                @if (! $finalizing && $cap > 0 && $crawl->pages_seen > 0)
                    <p class="mt-2 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                        {{ number_format($seen) }} of {{ number_format($cap) }} pages crawled
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
