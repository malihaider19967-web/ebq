<x-marketing.page
    title="Quick Start Guide — EBQ"
    description="Set up EBQ in under ten minutes: connect Search Console and Analytics, run your first audit, and schedule your first weekly report."
    active="guide"
>
    <article class="bg-white">
        {{-- ── Article hero ──────────────────────────────────────── --}}
        <header class="border-b border-slate-200">
            <div class="mx-auto max-w-3xl px-6 py-16 lg:px-8 lg:py-20">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Quick start guide · 8 min read</p>
                <h1 class="mt-4 text-balance text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
                    Get from signup to a sent report in ten minutes.
                </h1>
                <p class="mt-5 text-balance text-[17px] leading-8 text-slate-600">
                    EBQ is built around a weekly rhythm — discover, prioritize, execute, measure. This guide walks you through the first run of that loop on your own data.
                </p>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-6 py-16 lg:px-8 lg:py-20">

            {{-- Quick anchor list --}}
            <nav aria-label="Steps" class="mb-14 rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">In this guide</p>
                <ol class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    @foreach ([
                        ['#step-1', '1', 'Add your first website'],
                        ['#step-2', '2', 'Connect Search Console + GA4'],
                        ['#step-3', '3', 'Track keywords and competitors'],
                        ['#step-4', '4', 'Run a page audit'],
                        ['#step-5', '5', 'Review the insight boards'],
                        ['#step-6', '6', 'Schedule your first report'],
                    ] as [$href, $n, $label])
                        <li>
                            <a href="{{ $href }}" class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-slate-700 transition hover:bg-white hover:text-slate-900">
                                <span class="flex h-6 w-6 flex-none items-center justify-center rounded-md bg-white text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200">{{ $n }}</span>
                                <span class="font-medium">{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </nav>

            <div class="prose prose-slate max-w-none">
                @php
                    $steps = [
                        [
                            'id' => 'step-1',
                            'n' => '01',
                            'time' => '~ 1 min',
                            'title' => 'Add your first website',
                            'body' => 'From the dashboard, choose <strong>Websites → Add website</strong> and paste your canonical URL. EBQ will probe robots.txt, sitemaps, and indexable structure automatically.',
                            'tip' => 'Tip: use the exact protocol and host registered in Search Console. EBQ matches GSC properties by URL prefix.',
                        ],
                        [
                            'id' => 'step-2',
                            'n' => '02',
                            'time' => '~ 2 min',
                            'title' => 'Connect Search Console + GA4',
                            'body' => 'Open <strong>Settings → Integrations</strong> and click <em>Connect Google</em>. EBQ requests <code>analytics.readonly</code>, <code>webmasters.readonly</code>, and <code>indexing</code>. Tokens are encrypted and refreshed automatically.',
                            'tip' => 'You can grant only one source if you prefer — EBQ will still operate, with reduced cross-signal coverage.',
                        ],
                        [
                            'id' => 'step-3',
                            'n' => '03',
                            'time' => '~ 2 min',
                            'title' => 'Track keywords and competitors',
                            'body' => 'In <strong>Keywords</strong>, paste up to your plan limit. Set country, device, and (optionally) up to three competitors. The first SERP capture runs within a few minutes.',
                            'tip' => 'Start with 20–40 high-intent terms. You can always add more — empty rows aren\'t penalized.',
                        ],
                        [
                            'id' => 'step-4',
                            'n' => '04',
                            'time' => '~ 1 min',
                            'title' => 'Run a page audit',
                            'body' => 'Open <strong>Pages → Audits → New audit</strong>. Pick the URL, select mobile or desktop, optionally provide the target keyword. Audits combine Core Web Vitals + on-page SEO + content review and finish in &lt; 60 seconds.',
                            'tip' => 'Audits with a target keyword unlock the keyword-strategy review and topical-gap analysis.',
                        ],
                        [
                            'id' => 'step-5',
                            'n' => '05',
                            'time' => '~ 2 min',
                            'title' => 'Review the insight boards',
                            'body' => 'Visit <strong>Dashboard → Insights</strong>. The six boards (cannibalization, striking distance, content decay, indexing fails, audit-vs-traffic, backlink impact) populate within a few minutes of GSC sync. Each row links straight to the offending page.',
                            'tip' => 'Most teams ship 1–3 wins from striking-distance the first week.',
                        ],
                        [
                            'id' => 'step-6',
                            'n' => '06',
                            'time' => '~ 1 min',
                            'title' => 'Schedule your first report',
                            'body' => 'In <strong>Reports → Schedules</strong> add recipient emails, pick weekly cadence, and a delivery time. EBQ generates a fresh report every cycle with action insights, YoY deltas, and top gainers/losers.',
                            'tip' => 'Add a stakeholder address and yourself — even unread reports build a reviewable history.',
                        ],
                    ];
                @endphp

                @foreach ($steps as $step)
                    <section id="{{ $step['id'] }}" class="not-prose mt-12 first:mt-0">
                        <div class="flex items-baseline gap-4">
                            <span class="font-mono text-sm font-semibold text-slate-400">{{ $step['n'] }}</span>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-600">{{ $step['time'] }}</span>
                        </div>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">{{ $step['title'] }}</h2>
                        <p class="mt-4 text-[16px] leading-7 text-slate-600">{!! $step['body'] !!}</p>
                        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50/60 px-5 py-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Tip</p>
                            <p class="mt-1 text-sm leading-6 text-slate-700">{{ $step['tip'] }}</p>
                        </div>
                    </section>
                @endforeach

                {{-- Operational checklist --}}
                <section class="not-prose mt-16">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Operational checklist</h2>
                    <p class="mt-3 text-[15px] leading-7 text-slate-600">A short repeatable rhythm we recommend running every week.</p>
                    <ul class="mt-6 space-y-3 text-[14px]">
                        @foreach ([
                            'Open the dashboard. Read the action insights panel first.',
                            'Triage the cannibalization and striking-distance boards.',
                            'Pick 1–3 actions and assign with one-click ticket export.',
                            'Re-run an audit on every page you ship a fix to.',
                            'Read your scheduled weekly report — note YoY direction.',
                        ] as $item)
                            <li class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-md border border-slate-300 bg-white"></span>
                                <span class="text-slate-700">{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        </div>
    </article>

    {{-- ── CTA ──────────────────────────────────────────────── --}}
    <section class="bg-slate-50/60 py-20 sm:py-24">
        <div class="mx-auto max-w-3xl px-6 text-center lg:px-8">
            <h2 class="text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Ready to run the loop on your data?</h2>
            <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-600">Connect your first website and complete the six steps in under ten minutes.</p>
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Start free</a>
                <a href="{{ route('features') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900">See features</a>
            </div>
        </div>
    </section>
</x-marketing.page>
