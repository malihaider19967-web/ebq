@php
    $result = $auditReport->result ?? [];
    $meta = $result['metadata'] ?? [];
    $content = $result['content'] ?? [];
    $images = $result['images'] ?? [];
    $links = $result['links'] ?? [];
    $technical = $result['technical'] ?? [];
    $advanced = $result['advanced'] ?? [];
    $failed = $auditReport->status === 'failed';
@endphp

<div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <details class="group">
        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-800 [&::-webkit-details-marker]:hidden">
            <div class="flex items-center gap-2">
                <svg class="h-3.5 w-3.5 text-slate-500 transition-transform group-open:rotate-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Page Audit Report</h2>
                <span @class([
                    'inline-flex rounded-full px-2 py-px text-[10px] font-semibold',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => ! $failed,
                    'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' => $failed,
                ])>{{ ucfirst($auditReport->status) }}</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                Last audited: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $auditReport->audited_at?->format('M j, Y g:i A') ?? '—' }}</span>
            </p>
        </summary>

    @if ($failed)
        <div class="px-4 py-3 text-xs text-rose-600 dark:text-rose-400">
            Audit failed: {{ $auditReport->error_message ?? 'Unknown error' }}
        </div>
    @else
        <div class="space-y-5 px-4 py-4">
            {{-- 1. Metadata --}}
            <section>
                <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">1. Metadata</h3>
                <div class="grid gap-2 text-xs sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">Title <span class="font-normal text-slate-500">({{ $meta['title_length'] ?? 0 }} chars)</span></p>
                        <p class="mt-1 break-words text-slate-600 dark:text-slate-300">{{ $meta['title'] ?? '—' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">Meta Description <span class="font-normal text-slate-500">({{ $meta['meta_description_length'] ?? 0 }} chars)</span></p>
                        <p class="mt-1 break-words text-slate-600 dark:text-slate-300">{{ $meta['meta_description'] ?? '—' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">Canonical URL</p>
                        <p class="mt-1 break-all text-slate-600 dark:text-slate-300">{{ $meta['canonical'] ?? '—' }}</p>
                        <p class="mt-1 text-[11px] {{ ($meta['canonical_matches'] ?? false) ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ ($meta['canonical_matches'] ?? false) ? 'Matches page URL' : 'Does not match page URL' }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">Social tags</p>
                        <p class="mt-1 text-slate-600 dark:text-slate-300">OpenGraph: <strong>{{ $meta['og_tag_count'] ?? 0 }}</strong> &nbsp;·&nbsp; Twitter: <strong>{{ $meta['twitter_tag_count'] ?? 0 }}</strong></p>
                    </div>
                </div>
            </section>

            {{-- 2. Content & Structure --}}
            <section>
                <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">2. Content &amp; Structure</h3>
                <div class="grid gap-2 text-xs sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">H1 count</p><p class="mt-1 text-lg font-bold {{ ($content['h1_count'] ?? 0) === 1 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $content['h1_count'] ?? 0 }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Heading order</p><p class="mt-1 text-sm font-bold {{ ($content['heading_order_ok'] ?? false) ? 'text-emerald-600' : 'text-amber-600' }}">{{ ($content['heading_order_ok'] ?? false) ? 'Logical' : 'Skipped levels' }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Word count</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ number_format($content['word_count'] ?? 0) }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Headings</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ count($content['headings'] ?? []) }}</p></div>
                </div>
                @if (! empty($content['first_150_words']))
                    <div class="mt-2 rounded-lg border border-slate-200 p-3 text-xs dark:border-slate-700">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">Answer readiness (first 150 words)</p>
                        <p class="mt-1 text-slate-600 dark:text-slate-300">{{ $content['first_150_words'] }}</p>
                    </div>
                @endif
                @if (! empty($content['keyword_density']))
                    <div class="mt-2 rounded-lg border border-slate-200 p-3 text-xs dark:border-slate-700">
                        <p class="mb-2 font-semibold text-slate-700 dark:text-slate-200">Keyword density (top 20)</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($content['keyword_density'] as $kw)
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    <strong>{{ $kw['term'] }}</strong>
                                    <span class="text-slate-500">×{{ $kw['count'] }} · {{ $kw['density'] }}%</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if (! empty($content['headings']))
                    <details class="mt-2 rounded-lg border border-slate-200 text-xs dark:border-slate-700">
                        <summary class="cursor-pointer px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">Heading outline ({{ count($content['headings']) }})</summary>
                        <ul class="space-y-0.5 border-t border-slate-200 px-3 py-2 dark:border-slate-700">
                            @foreach ($content['headings'] as $h)
                                <li class="text-slate-600 dark:text-slate-300" style="padding-left: {{ ($h['level'] - 1) * 12 }}px;">
                                    <span class="font-mono text-[10px] text-slate-500">H{{ $h['level'] }}</span> {{ $h['text'] }}
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>

            {{-- 3. Image & Link Analysis --}}
            <section>
                <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">3. Image &amp; Link Analysis</h3>
                <div class="grid gap-2 text-xs sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Images total</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $images['total'] ?? 0 }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Missing alt</p><p class="mt-1 text-lg font-bold {{ ($images['missing_alt_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $images['missing_alt_count'] ?? 0 }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Modern formats (webp/avif)</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $images['modern_format_count'] ?? 0 }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Broken links</p><p class="mt-1 text-lg font-bold {{ count($links['broken'] ?? []) > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ count($links['broken'] ?? []) }}</p></div>
                </div>
                <div class="mt-2 grid gap-2 text-xs sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-slate-500">Internal links <span class="font-semibold text-slate-700 dark:text-slate-200">({{ $links['internal_count'] ?? 0 }})</span></p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-slate-500">External links <span class="font-semibold text-slate-700 dark:text-slate-200">({{ $links['external_count'] ?? 0 }})</span></p>
                    </div>
                </div>
                @if (! empty($images['missing_alt']))
                    <details class="mt-2 rounded-lg border border-slate-200 text-xs dark:border-slate-700">
                        <summary class="cursor-pointer px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">Images missing alt</summary>
                        <ul class="max-h-48 space-y-0.5 overflow-auto border-t border-slate-200 px-3 py-2 font-mono text-[11px] dark:border-slate-700">
                            @foreach ($images['missing_alt'] as $src)
                                <li class="truncate text-slate-600 dark:text-slate-300">{{ $src }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
                @if (! empty($links['broken']))
                    <details class="mt-2 rounded-lg border border-rose-200 bg-rose-50/40 text-xs dark:border-rose-900/40 dark:bg-rose-500/5" open>
                        <summary class="cursor-pointer px-3 py-2 font-semibold text-rose-700 dark:text-rose-400">Broken links ({{ count($links['broken']) }})</summary>
                        <ul class="max-h-64 space-y-1 overflow-auto border-t border-rose-200 px-3 py-2 dark:border-rose-900/40">
                            @foreach ($links['broken'] as $b)
                                <li class="flex items-start gap-2">
                                    <span class="inline-flex shrink-0 rounded bg-rose-100 px-1.5 py-px font-mono text-[10px] font-bold text-rose-700 dark:bg-rose-500/20 dark:text-rose-400">{{ $b['status'] ?? 'ERR' }}</span>
                                    <a href="{{ $b['href'] }}" target="_blank" rel="noopener" class="break-all text-rose-700 underline dark:text-rose-400">{{ $b['href'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
                @if (! empty($links['internal']) || ! empty($links['external']))
                    <details class="mt-2 rounded-lg border border-slate-200 text-xs dark:border-slate-700">
                        <summary class="cursor-pointer px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">All links ({{ ($links['internal_count'] ?? 0) + ($links['external_count'] ?? 0) }})</summary>
                        <div class="max-h-64 space-y-2 overflow-auto border-t border-slate-200 px-3 py-2 dark:border-slate-700">
                            @if (! empty($links['internal']))
                                <div>
                                    <p class="mb-1 text-[10px] font-bold uppercase text-slate-500">Internal</p>
                                    <ul class="space-y-0.5">
                                        @foreach ($links['internal'] as $l)
                                            <li class="truncate text-slate-600 dark:text-slate-300"><a href="{{ $l['href'] }}" target="_blank" rel="noopener" class="hover:underline">{{ $l['href'] }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if (! empty($links['external']))
                                <div>
                                    <p class="mb-1 text-[10px] font-bold uppercase text-slate-500">External</p>
                                    <ul class="space-y-0.5">
                                        @foreach ($links['external'] as $l)
                                            <li class="truncate text-slate-600 dark:text-slate-300"><a href="{{ $l['href'] }}" target="_blank" rel="noopener" class="hover:underline">{{ $l['href'] }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </details>
                @endif
            </section>

            {{-- 4. Technical Performance --}}
            <section>
                <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">4. Technical Performance</h3>
                <div class="grid gap-2 text-xs sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">HTTP status</p><p class="mt-1 text-lg font-bold {{ ($technical['http_status'] ?? 0) < 400 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $technical['http_status'] ?? '—' }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Response time (TTFB)</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ isset($technical['ttfb_ms']) ? $technical['ttfb_ms'].' ms' : '—' }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Page size</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ isset($technical['page_size_bytes']) ? number_format($technical['page_size_bytes'] / 1024, 1).' KB' : '—' }}</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Compression / HTTPS</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-slate-100">{{ $technical['compression'] ?? 'none' }} · {{ ($technical['is_https'] ?? false) ? 'HTTPS' : 'HTTP' }}</p></div>
                </div>
            </section>

            {{-- 5. Advanced Data --}}
            <section>
                <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">5. Advanced Data</h3>
                <div class="grid gap-2 text-xs sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Schema (JSON-LD)</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $advanced['schema_blocks'] ?? 0 }} block(s)</p></div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <p class="text-slate-500">Readability (Flesch)</p>
                        <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ data_get($advanced, 'readability.flesch') ?? '—' }}</p>
                        <p class="text-[11px] text-slate-500">{{ data_get($advanced, 'readability.grade') ?? '' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"><p class="text-slate-500">Favicon</p><p class="mt-1 text-sm font-bold {{ ($advanced['has_favicon'] ?? false) ? 'text-emerald-600' : 'text-amber-600' }}">{{ ($advanced['has_favicon'] ?? false) ? 'Present' : 'Missing' }}</p></div>
                </div>
            </section>
            </div>
        @endif
    </details>

    <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/30">
        <a href="{{ route('page-audits.download', $auditReport->id) }}"
           class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Download report
        </a>

        <form wire:submit.prevent="emailAuditReport" class="flex flex-1 flex-wrap items-center gap-2 sm:flex-nowrap">
            <input type="email" wire:model="auditEmail" placeholder="recipient@example.com"
                   class="h-8 min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-700 placeholder-slate-400 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder-slate-500" />
            <button type="submit" wire:loading.attr="disabled" wire:target="emailAuditReport"
                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-indigo-500 bg-indigo-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                <span wire:loading.remove wire:target="emailAuditReport">Send via email</span>
                <span wire:loading wire:target="emailAuditReport">Sending…</span>
            </button>
        </form>
    </div>
</div>
