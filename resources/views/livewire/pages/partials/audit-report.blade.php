@php
    $result = $auditReport->result ?? [];
    $meta = $result['metadata'] ?? [];
    $content = $result['content'] ?? [];
    $images = $result['images'] ?? [];
    $links = $result['links'] ?? [];
    $technical = $result['technical'] ?? [];
    $advanced = $result['advanced'] ?? [];
    $recs = $result['recommendations'] ?? [];
    $failed = $auditReport->status === 'failed';

    $counts = collect($recs)->groupBy('severity')->map->count();
    $critical = (int) ($counts['critical'] ?? 0);
    $warning = (int) ($counts['warning'] ?? 0);
    $info = (int) ($counts['info'] ?? 0);
    $good = (int) ($counts['good'] ?? 0);

    $score = $failed ? 0 : max(0, 100 - ($critical * 15) - ($warning * 6) - ($info * 2));
    $scoreTone = $score >= 85 ? 'good' : ($score >= 65 ? 'warn' : 'bad');
    $scoreStrokeClass = $scoreTone === 'good' ? 'text-emerald-500' : ($scoreTone === 'warn' ? 'text-amber-500' : 'text-rose-500');
    $scoreBadgeClass = $scoreTone === 'good'
        ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-900/40'
        : ($scoreTone === 'warn'
            ? 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-900/40'
            : 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-900/40');
    $scoreLabel = $score >= 85 ? 'Healthy' : ($score >= 65 ? 'Needs attention' : 'Critical');
    $circumference = 2 * M_PI * 28;
    $dashOffset = $circumference - ($score / 100) * $circumference;

    $sevMeta = [
        'critical' => ['label' => 'Critical', 'badge' => 'bg-rose-100 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-900/40', 'bar' => 'border-l-rose-500 bg-rose-50/40 dark:bg-rose-500/5', 'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
        'warning'  => ['label' => 'Warning',  'badge' => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-900/40', 'bar' => 'border-l-amber-500 bg-amber-50/40 dark:bg-amber-500/5', 'icon' => 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z'],
        'info'     => ['label' => 'Info',     'badge' => 'bg-sky-100 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-900/40',          'bar' => 'border-l-sky-500 bg-sky-50/40 dark:bg-sky-500/5',     'icon' => 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z'],
        'good'     => ['label' => 'Good',     'badge' => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-900/40', 'bar' => 'border-l-emerald-500 bg-emerald-50/40 dark:bg-emerald-500/5', 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];

    $sections = [
        ['key' => 'recommendations', 'label' => 'Recommendations', 'count' => count($recs), 'show' => ! empty($recs)],
        ['key' => 'metadata',        'label' => 'Metadata',        'count' => null,         'show' => true],
        ['key' => 'content',         'label' => 'Content',         'count' => null,         'show' => true],
        ['key' => 'links',           'label' => 'Images & Links',  'count' => null,         'show' => true],
        ['key' => 'technical',       'label' => 'Technical',       'count' => null,         'show' => true],
        ['key' => 'advanced',        'label' => 'Advanced',        'count' => null,         'show' => true],
    ];
@endphp

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <details class="group">
        {{-- ═══ Report Header ═══ --}}
        <summary class="flex cursor-pointer list-none items-start gap-4 border-b border-slate-200 bg-gradient-to-br from-slate-50 to-white px-5 py-4 dark:border-slate-800 dark:from-slate-900 dark:to-slate-900 [&::-webkit-details-marker]:hidden">
            {{-- Score donut --}}
            @if (! $failed)
                <div class="relative hidden shrink-0 sm:block">
                    <svg class="h-16 w-16 -rotate-90" viewBox="0 0 64 64">
                        <circle cx="32" cy="32" r="28" fill="none" stroke="currentColor" stroke-width="6" class="text-slate-200 dark:text-slate-800" />
                        <circle cx="32" cy="32" r="28" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round"
                                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $dashOffset }}"
                                class="{{ $scoreStrokeClass }} transition-all" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-lg font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ $score }}</span>
                        <span class="text-[9px] font-semibold uppercase tracking-wider text-slate-500">score</span>
                    </div>
                </div>
            @endif

            {{-- Title + badges --}}
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <svg class="h-3.5 w-3.5 text-slate-500 transition-transform group-open:rotate-90" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    <h2 class="text-base font-bold tracking-tight text-slate-900 dark:text-slate-100">Page Audit Report</h2>
                    @if ($failed)
                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-900/40">
                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            Failed
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 {{ $scoreBadgeClass }}">
                            {{ $scoreLabel }}
                        </span>
                    @endif
                </div>

                {{-- Severity pills --}}
                @if (! $failed && ! empty($recs))
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach (['critical', 'warning', 'info', 'good'] as $sev)
                            @if (($counts[$sev] ?? 0) > 0)
                                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold {{ $sevMeta[$sev]['badge'] }}">
                                    <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sevMeta[$sev]['icon'] }}" /></svg>
                                    {{ $counts[$sev] }} {{ $sevMeta[$sev]['label'] }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                @endif

                <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                    Audited <span class="font-medium text-slate-700 dark:text-slate-300">{{ $auditReport->audited_at?->diffForHumans() ?? '—' }}</span>
                    @if ($auditReport->audited_at)
                        <span class="text-slate-400">·</span> {{ $auditReport->audited_at->format('M j, Y g:i A') }}
                    @endif
                </p>
            </div>
        </summary>

        @if ($failed)
            <div class="flex items-start gap-3 px-5 py-4 text-sm">
                <svg class="h-5 w-5 shrink-0 text-rose-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                <div>
                    <p class="font-semibold text-slate-900 dark:text-slate-100">Audit failed</p>
                    <p class="mt-0.5 text-slate-600 dark:text-slate-400">{{ $auditReport->error_message ?? 'Unknown error' }}</p>
                </div>
            </div>
        @else
            {{-- ═══ Section nav ═══ --}}
            <nav class="sticky top-0 z-10 flex gap-1 overflow-x-auto border-b border-slate-200 bg-white/95 px-5 py-2 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
                @foreach ($sections as $s)
                    @if ($s['show'])
                        <a href="#audit-{{ $s['key'] }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100">
                            {{ $s['label'] }}
                            @if ($s['count'] !== null)
                                <span class="rounded-full bg-slate-200 px-1.5 text-[9px] font-bold tabular-nums text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $s['count'] }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </nav>

            <div class="space-y-6 px-5 py-5">

                {{-- ══════ Recommendations ══════ --}}
                @if (! empty($recs))
                    <section id="audit-recommendations" class="scroll-mt-16">
                        <div class="mb-3 flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-100 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" /></svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Recommendations</h3>
                            <span class="ml-auto text-[11px] text-slate-500 dark:text-slate-400">{{ count($recs) }} item{{ count($recs) === 1 ? '' : 's' }}</span>
                        </div>
                        <div class="space-y-2">
                            @foreach ($recs as $r)
                                @php $sm = $sevMeta[$r['severity']] ?? $sevMeta['info']; @endphp
                                <div class="flex gap-3 rounded-lg border border-slate-200 border-l-4 {{ $sm['bar'] }} p-3 transition hover:shadow-sm dark:border-slate-700">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $r['severity'] === 'critical' ? 'text-rose-500' : ($r['severity'] === 'warning' ? 'text-amber-500' : ($r['severity'] === 'info' ? 'text-sky-500' : 'text-emerald-500')) }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sm['icon'] }}" /></svg>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $r['title'] }}</p>
                                            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $r['section'] }}</span>
                                        </div>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $r['why'] }}</p>
                                        <p class="mt-1.5 text-xs leading-relaxed text-slate-700 dark:text-slate-200"><span class="font-semibold">Fix:</span> {{ $r['fix'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- ══════ Metadata ══════ --}}
                <section id="audit-metadata" class="scroll-mt-16">
                    <div class="mb-3 flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-md bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8.25V18a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 18V8.25m-18 0V6a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 6v2.25m-18 0h18M5.25 6h.008v.008H5.25V6zM7.5 6h.008v.008H7.5V6zm2.25 0h.008v.008H9.75V6z" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Metadata</h3>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        {{-- Title --}}
                        @php $titleLen = $meta['title_length'] ?? 0; $titleOk = $titleLen >= 30 && $titleLen <= 60; $titleBad = $titleLen === 0 || $titleLen > 60; @endphp
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                            <div class="flex items-center justify-between">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Title tag</p>
                                <span class="text-[10px] font-bold tabular-nums {{ $titleOk ? 'text-emerald-600' : ($titleBad ? 'text-rose-600' : 'text-amber-600') }}">{{ $titleLen }} / 60</span>
                            </div>
                            <p class="mt-1.5 break-words text-sm text-slate-800 dark:text-slate-100">{{ $meta['title'] ?? '—' }}</p>
                            <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                <div class="h-full rounded-full transition-all {{ $titleOk ? 'bg-emerald-500' : ($titleBad ? 'bg-rose-500' : 'bg-amber-500') }}" style="width: {{ min(100, ($titleLen / 60) * 100) }}%"></div>
                            </div>
                        </div>
                        {{-- Description --}}
                        @php $descLen = $meta['meta_description_length'] ?? 0; $descOk = $descLen >= 120 && $descLen <= 160; $descBad = $descLen === 0 || $descLen > 160; @endphp
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                            <div class="flex items-center justify-between">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Meta description</p>
                                <span class="text-[10px] font-bold tabular-nums {{ $descOk ? 'text-emerald-600' : ($descBad ? 'text-rose-600' : 'text-amber-600') }}">{{ $descLen }} / 160</span>
                            </div>
                            <p class="mt-1.5 break-words text-sm text-slate-800 dark:text-slate-100">{{ $meta['meta_description'] ?? '—' }}</p>
                            <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                <div class="h-full rounded-full transition-all {{ $descOk ? 'bg-emerald-500' : ($descBad ? 'bg-rose-500' : 'bg-amber-500') }}" style="width: {{ min(100, ($descLen / 160) * 100) }}%"></div>
                            </div>
                        </div>
                        {{-- Canonical --}}
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                            <div class="flex items-center justify-between">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Canonical URL</p>
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold {{ ($meta['canonical_matches'] ?? false) ? 'text-emerald-600' : 'text-amber-600' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ ($meta['canonical_matches'] ?? false) ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                    {{ ($meta['canonical_matches'] ?? false) ? 'Matches' : 'Mismatch' }}
                                </span>
                            </div>
                            <p class="mt-1.5 break-all text-sm text-slate-800 dark:text-slate-100">{{ $meta['canonical'] ?? '—' }}</p>
                        </div>
                        {{-- Social --}}
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Social sharing tags</p>
                            <div class="mt-2 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-[10px] text-slate-500">OpenGraph</p>
                                    <p class="text-lg font-bold tabular-nums {{ ($meta['og_tag_count'] ?? 0) > 0 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $meta['og_tag_count'] ?? 0 }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-500">Twitter</p>
                                    <p class="text-lg font-bold tabular-nums {{ ($meta['twitter_tag_count'] ?? 0) > 0 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $meta['twitter_tag_count'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ══════ Content ══════ --}}
                <section id="audit-content" class="scroll-mt-16">
                    <div class="mb-3 flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-md bg-violet-100 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Content &amp; Structure</h3>
                    </div>
                    <div class="grid gap-3 grid-cols-2 md:grid-cols-4">
                        @php $h1 = $content['h1_count'] ?? 0; $orderOk = $content['heading_order_ok'] ?? false; $wc = $content['word_count'] ?? 0; @endphp
                        <x-audit.stat label="H1 count" :value="$h1" :tone="$h1 === 1 ? 'good' : 'bad'" />
                        <x-audit.stat label="Heading order" :value="$orderOk ? 'Logical' : 'Skipped'" :tone="$orderOk ? 'good' : 'bad'" />
                        <x-audit.stat label="Word count" :value="number_format($wc)" :tone="$wc >= 300 ? 'good' : ($wc === 0 ? 'bad' : 'warn')" />
                        <x-audit.stat label="Headings total" :value="count($content['headings'] ?? [])" tone="neutral" />
                    </div>

                    @if (! empty($content['first_150_words']))
                        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-800/30">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Answer readiness — first 150 words</p>
                            <p class="mt-2 text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $content['first_150_words'] }}</p>
                        </div>
                    @endif

                    @if (! empty($content['keyword_density']))
                        <div class="mt-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Keyword density — top 20</p>
                                @php $topDensity = $content['keyword_density'][0]['density'] ?? 0; @endphp
                                @if ($topDensity >= 3)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">Stuffing risk</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($content['keyword_density'] as $kw)
                                    @php $d = (float) $kw['density']; $cls = $d >= 3 ? 'bg-rose-100 text-rose-700 ring-rose-200' : ($d >= 1.5 ? 'bg-amber-100 text-amber-700 ring-amber-200' : 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'); @endphp
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] ring-1 {{ $cls }}">
                                        <strong>{{ $kw['term'] }}</strong>
                                        <span class="opacity-70">×{{ $kw['count'] }} · {{ $kw['density'] }}%</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (! empty($content['headings']))
                        <details class="group/outline mt-3 rounded-lg border border-slate-200 dark:border-slate-700">
                            <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500 [&::-webkit-details-marker]:hidden">
                                <span>Heading outline · {{ count($content['headings']) }}</span>
                                <svg class="h-3 w-3 transition-transform group-open/outline:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </summary>
                            <ul class="max-h-72 space-y-0.5 overflow-auto border-t border-slate-200 px-3 py-2 text-xs dark:border-slate-700">
                                @foreach ($content['headings'] as $h)
                                    <li class="text-slate-700 dark:text-slate-300" style="padding-left: {{ ($h['level'] - 1) * 14 }}px;">
                                        <span class="mr-1.5 inline-flex h-4 w-6 items-center justify-center rounded bg-slate-100 font-mono text-[9px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-400">H{{ $h['level'] }}</span>
                                        {{ $h['text'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </section>

                {{-- ══════ Images & Links ══════ --}}
                <section id="audit-links" class="scroll-mt-16">
                    <div class="mb-3 flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-md bg-pink-100 text-pink-600 dark:bg-pink-500/10 dark:text-pink-400">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Images &amp; Links</h3>
                    </div>
                    <div class="grid gap-3 grid-cols-2 md:grid-cols-4">
                        <x-audit.stat label="Images" :value="$images['total'] ?? 0" tone="neutral" />
                        <x-audit.stat label="Missing alt" :value="$images['missing_alt_count'] ?? 0" :tone="($images['missing_alt_count'] ?? 0) > 0 ? 'bad' : 'good'" />
                        <x-audit.stat label="Modern formats" :value="$images['modern_format_count'] ?? 0" tone="neutral" />
                        <x-audit.stat label="Broken links" :value="count($links['broken'] ?? [])" :tone="count($links['broken'] ?? []) > 0 ? 'bad' : 'good'" />
                        <x-audit.stat label="Internal links" :value="$links['internal_count'] ?? 0" tone="neutral" />
                        <x-audit.stat label="External links" :value="$links['external_count'] ?? 0" tone="neutral" />
                    </div>

                    @if (! empty($links['broken']))
                        <details class="group/broken mt-3 overflow-hidden rounded-lg border border-rose-200 bg-rose-50/40 dark:border-rose-900/40 dark:bg-rose-500/5" open>
                            <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-400 [&::-webkit-details-marker]:hidden">
                                <span class="flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                                    Broken links · {{ count($links['broken']) }}
                                </span>
                                <svg class="h-3 w-3 transition-transform group-open/broken:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </summary>
                            <ul class="max-h-72 space-y-1 overflow-auto border-t border-rose-200 px-3 py-2 dark:border-rose-900/40">
                                @foreach ($links['broken'] as $b)
                                    <li class="flex items-start gap-2">
                                        <span class="inline-flex shrink-0 rounded bg-rose-200 px-1.5 py-px font-mono text-[10px] font-bold text-rose-800 dark:bg-rose-500/30 dark:text-rose-300">{{ $b['status'] ?? 'ERR' }}</span>
                                        <a href="{{ $b['href'] }}" target="_blank" rel="noopener" class="break-all text-xs text-rose-700 underline dark:text-rose-400">{{ $b['href'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    @if (! empty($images['missing_alt']))
                        <details class="group/alt mt-3 rounded-lg border border-slate-200 dark:border-slate-700">
                            <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500 [&::-webkit-details-marker]:hidden">
                                <span>Images missing alt · {{ count($images['missing_alt']) }}</span>
                                <svg class="h-3 w-3 transition-transform group-open/alt:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </summary>
                            <ul class="max-h-48 space-y-0.5 overflow-auto border-t border-slate-200 px-3 py-2 font-mono text-[11px] dark:border-slate-700">
                                @foreach ($images['missing_alt'] as $src)
                                    <li class="truncate text-slate-600 dark:text-slate-300">{{ $src }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    @if (! empty($links['internal']) || ! empty($links['external']))
                        <details class="group/all mt-3 rounded-lg border border-slate-200 dark:border-slate-700">
                            <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500 [&::-webkit-details-marker]:hidden">
                                <span>All links · {{ ($links['internal_count'] ?? 0) + ($links['external_count'] ?? 0) }}</span>
                                <svg class="h-3 w-3 transition-transform group-open/all:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </summary>
                            <div class="max-h-80 space-y-3 overflow-auto border-t border-slate-200 px-3 py-2 dark:border-slate-700">
                                @if (! empty($links['internal']))
                                    <div>
                                        <p class="mb-1 text-[10px] font-bold uppercase text-slate-500">Internal · {{ count($links['internal']) }}</p>
                                        <ul class="space-y-0.5">
                                            @foreach ($links['internal'] as $l)
                                                <li class="truncate text-xs text-slate-600 dark:text-slate-300"><a href="{{ $l['href'] }}" target="_blank" rel="noopener" class="hover:underline">{{ $l['href'] }}</a></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @if (! empty($links['external']))
                                    <div>
                                        <p class="mb-1 text-[10px] font-bold uppercase text-slate-500">External · {{ count($links['external']) }}</p>
                                        <ul class="space-y-0.5">
                                            @foreach ($links['external'] as $l)
                                                <li class="truncate text-xs text-slate-600 dark:text-slate-300"><a href="{{ $l['href'] }}" target="_blank" rel="noopener" class="hover:underline">{{ $l['href'] }}</a></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endif
                </section>

                {{-- ══════ Technical ══════ --}}
                <section id="audit-technical" class="scroll-mt-16">
                    <div class="mb-3 flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-md bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Technical Performance</h3>
                    </div>
                    @php
                        $httpStatus = $technical['http_status'] ?? 0;
                        $ttfb = $technical['ttfb_ms'] ?? null;
                        $size = $technical['page_size_bytes'] ?? null;
                        $compression = $technical['compression'] ?? 'none';
                        $https = $technical['is_https'] ?? false;
                    @endphp
                    <div class="grid gap-3 grid-cols-2 md:grid-cols-5">
                        <x-audit.stat label="HTTP status" :value="$httpStatus ?: '—'" :tone="$httpStatus > 0 && $httpStatus < 400 ? 'good' : 'bad'" />
                        <x-audit.stat label="Response time" :value="$ttfb !== null ? $ttfb.' ms' : '—'" :tone="$ttfb === null ? 'neutral' : ($ttfb < 500 ? 'good' : ($ttfb < 1000 ? 'warn' : 'bad'))" />
                        <x-audit.stat label="Page size" :value="$size !== null ? number_format($size / 1024, 1).' KB' : '—'" tone="neutral" />
                        <x-audit.stat label="Compression" :value="$compression === '' || $compression === 'none' ? 'None' : strtoupper($compression)" :tone="in_array($compression, ['br', 'brotli']) ? 'good' : ($compression === 'gzip' ? 'warn' : 'bad')" />
                        <x-audit.stat label="HTTPS" :value="$https ? 'Secure' : 'Insecure'" :tone="$https ? 'good' : 'bad'" />
                    </div>
                </section>

                {{-- ══════ Advanced ══════ --}}
                <section id="audit-advanced" class="scroll-mt-16">
                    <div class="mb-3 flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-md bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 012.25-2.25h7.5A2.25 2.25 0 0118 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 004.5 9v.878m13.5-3A2.25 2.25 0 0119.5 9v.878m0 0a2.246 2.246 0 00-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0121 12v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6c0-.98.626-1.813 1.5-2.122" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Advanced Data</h3>
                    </div>
                    @php $flesch = data_get($advanced, 'readability.flesch'); $fleschTone = ! is_numeric($flesch) ? 'neutral' : ($flesch >= 60 ? 'good' : ($flesch >= 30 ? 'warn' : 'bad')); @endphp
                    <div class="grid gap-3 grid-cols-2 md:grid-cols-3">
                        <x-audit.stat label="Schema (JSON-LD)" :value="($advanced['schema_blocks'] ?? 0).' block(s)'" :tone="($advanced['schema_blocks'] ?? 0) > 0 ? 'good' : 'warn'" />
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Readability (Flesch)</p>
                            <p class="mt-1 text-xl font-bold tabular-nums {{ $fleschTone === 'good' ? 'text-emerald-600' : ($fleschTone === 'warn' ? 'text-amber-600' : ($fleschTone === 'bad' ? 'text-rose-600' : 'text-slate-800 dark:text-slate-100')) }}">{{ $flesch ?? '—' }}</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ data_get($advanced, 'readability.grade') ?? '' }}</p>
                        </div>
                        <x-audit.stat label="Favicon" :value="($advanced['has_favicon'] ?? false) ? 'Present' : 'Missing'" :tone="($advanced['has_favicon'] ?? false) ? 'good' : 'warn'" />
                    </div>
                </section>

            </div>
        @endif
    </details>

    {{-- ═══ Footer toolbar ═══ --}}
    <div class="flex flex-col gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-800/30">
        <a href="{{ route('page-audits.download', $auditReport->id) }}"
           class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Download
        </a>

        <form wire:submit.prevent="emailAuditReport" class="flex flex-1 items-center gap-2">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                <input type="email" wire:model="auditEmail" placeholder="recipient@example.com"
                       class="h-9 w-full rounded-md border border-slate-300 bg-white pl-8 pr-2.5 text-xs text-slate-700 placeholder-slate-400 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder-slate-500" />
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="emailAuditReport"
                    class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-md bg-indigo-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12zm0 0h7.5" /></svg>
                <span wire:loading.remove wire:target="emailAuditReport">Email report</span>
                <span wire:loading wire:target="emailAuditReport">Sending…</span>
            </button>
        </form>
    </div>
</div>
