<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">

    @include('partials.favicon-links')

    <title>Features - EBQ SEO Operations Platform</title>
    <meta name="description" content="Every capability inside EBQ — cross-signal insights, rank tracking, anomaly alerts, page audits with Core Web Vitals, backlink verification, and automated growth reports.">
    <link rel="canonical" href="{{ url('/features') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="Features - EBQ">
    <meta property="og:description" content="Cross-signal insights, rank tracking, anomaly alerts, audits, backlinks, automated reporting.">
    <meta property="og:url" content="{{ url('/features') }}">
    <meta property="og:site_name" content="EBQ">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @if (! app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-full bg-slate-950 font-sans text-slate-50 antialiased selection:bg-indigo-500/30 selection:text-white">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-950">Skip to content</a>

    <div class="relative overflow-x-clip">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[34rem] bg-[radial-gradient(circle_at_20%_0,rgba(99,102,241,0.28),transparent_45%),radial-gradient(circle_at_80%_0,rgba(14,165,233,0.22),transparent_40%)]"></div>

        <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/85 backdrop-blur">
            <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
                <a href="{{ route('landing') }}" class="flex items-center gap-3" aria-label="EBQ home">
                    <span aria-hidden="true" class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-cyan-400 text-xs font-bold text-white ring-1 ring-white/25">EBQ</span>
                    <span class="text-sm font-semibold uppercase tracking-[0.2em] text-white">EBQ</span>
                </a>

                <nav aria-label="Primary" class="hidden items-center gap-8 text-sm font-medium text-slate-100 md:flex">
                    <a href="{{ route('features') }}" class="text-white transition hover:text-indigo-200" aria-current="page">Features</a>
                    <a href="{{ route('pricing') }}" class="transition hover:text-indigo-200">Pricing</a>
                    <a href="{{ route('landing') }}#workflow" class="transition hover:text-indigo-200">Workflow</a>
                    <a href="{{ route('landing') }}#faq" class="transition hover:text-indigo-200">FAQ</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="hidden rounded-md px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 sm:inline-flex">Sign in</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">Start free</a>
                </div>
            </div>
        </header>

        <main id="main">
            {{-- Hero --}}
            <section class="mx-auto max-w-7xl px-6 pb-16 pt-14 lg:px-8 lg:pb-24 lg:pt-20">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="inline-flex items-center rounded-full border border-indigo-200/40 bg-indigo-500/15 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-100">
                        Every feature, one workspace
                    </p>
                    <h1 class="mt-6 text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl">
                        Ship SEO decisions, not dashboards
                    </h1>
                    <p class="mt-6 text-base leading-8 text-slate-100 sm:text-lg">
                        EBQ joins Search Console, Analytics, rank tracking, backlinks, and page audits into a single workspace that surfaces what to fix this week — and what to report up.
                    </p>
                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">Start free trial</a>
                        <a href="#insights" class="inline-flex items-center justify-center rounded-md border border-white/25 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">Explore features ↓</a>
                    </div>
                </div>

                {{-- Quick jump nav --}}
                <nav aria-label="Feature sections" class="mx-auto mt-14 flex max-w-4xl flex-wrap justify-center gap-2 text-xs font-medium">
                    @foreach ([
                        ['#insights', 'Cross-signal insights'],
                        ['#rank-tracking', 'Rank tracking'],
                        ['#alerts', 'Anomaly alerts'],
                        ['#audits', 'Page audits'],
                        ['#backlinks', 'Backlinks'],
                        ['#reporting', 'Reporting'],
                        ['#wordpress', 'WordPress plugin'],
                        ['#team', 'Team'],
                        ['#integrations', 'Integrations'],
                    ] as [$href, $label])
                        <a href="{{ $href }}" class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-slate-200 transition hover:border-indigo-300/40 hover:bg-indigo-500/15 hover:text-white">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </section>

            {{-- Feature category grid --}}
            <section class="bg-white py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">The short version</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Eight capability areas, built to work together</h2>
                        <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">
                            Every number you see ties back to an action. No orphan metrics.
                        </p>
                    </div>

                    @php
                        $pillars = [
                            ['icon' => 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z', 'title' => 'Cross-signal insights', 'desc' => 'Cannibalization, striking-distance, content decay, indexing fails, audit vs. traffic, and backlink impact — each produces an action list.', 'href' => '#insights'],
                            ['icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z', 'title' => 'Rank tracking', 'desc' => 'SERP-accurate positions, competitor tracking, PAA and related-search capture, device/country targeting — with GSC clicks overlaid on every keyword.', 'href' => '#rank-tracking'],
                            ['icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z', 'title' => 'Anomaly alerts', 'desc' => 'Statistical detection of traffic drops, session collapses, and rank regressions. Email alerts with per-metric baselines — deduped so inbox stays quiet.', 'href' => '#alerts'],
                            ['icon' => 'M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z', 'title' => 'Page audits', 'desc' => 'On-demand deep audits with full Core Web Vitals, content analysis, readability grade, technical SEO checks, and keyword strategy review.', 'href' => '#audits'],
                            ['icon' => 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244', 'title' => 'Backlinks', 'desc' => 'Bulk import, DA/spam score, anchor/rel verification, and per-target-page impact analysis that quantifies click lift after each link lands.', 'href' => '#backlinks'],
                            ['icon' => 'M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5', 'title' => 'Reporting', 'desc' => 'Daily / weekly / monthly growth reports with YoY comparisons, appended Action Insights, and on-demand custom ranges. Email-ready, white-label friendly.', 'href' => '#reporting'],
                            ['icon' => 'M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5', 'title' => 'WordPress plugin', 'desc' => 'Gutenberg sidebar, admin column, and dashboard widget that surface EBQ insights inside WordPress. Scoped Sanctum tokens, challenge-response verification.', 'href' => '#wordpress'],
                            ['icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z', 'title' => 'Team & permissions', 'desc' => 'Per-website roles, feature-level access control, and email invitations that auto-accept every pending invite on signup.', 'href' => '#team'],
                            ['icon' => 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582', 'title' => 'Integrations', 'desc' => 'Google Search Console, Google Analytics 4, and Google Indexing API — all authenticated and synced daily. Core Web Vitals and SERP data layered on top.', 'href' => '#integrations'],
                        ];
                    @endphp
                    <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($pillars as $p)
                            <a href="{{ $p['href'] }}" class="group rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
                                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 transition group-hover:bg-indigo-600 group-hover:text-white">
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $p['icon'] }}" /></svg>
                                </span>
                                <h3 class="mt-4 text-base font-semibold">{{ $p['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $p['desc'] }}</p>
                                <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-indigo-600">Learn more →</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Cross-signal insights --}}
            <section id="insights" class="bg-slate-50 py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">Cross-signal insights</p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Six reports that tell you what to do</h2>
                            <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">
                                Most SEO tools hand you raw data. EBQ joins the signals and surfaces the specific queries, pages, and links that need attention this week.
                            </p>
                            <dl class="mt-8 space-y-5">
                                @foreach ([
                                    ['Cannibalization', 'Queries where two or more of your pages split clicks. One URL should own the query — we tell you which, and which to re-target.'],
                                    ['Striking-distance', 'Queries at positions 5–20 with high impressions and below-curve CTR, ranked by an opportunity score. The fastest wins on your content calendar.'],
                                    ['Content decay', 'Pages losing clicks 28-over-28 while still earning impressions. We join Google\'s verdict so you can tell ranking decay from de-indexing.'],
                                    ['Indexing fails with traffic', 'Pages with a non-PASS verdict still pulling impressions — the urgent-action cohort.'],
                                    ['Audit vs. performance', 'Audited pages with weak Core Web Vitals that still attract real impressions — technical debt measurably costing traffic.'],
                                    ['Backlink impact', 'For every target page, clicks in the 28 days after the latest tracked backlink vs the 28 days before — sorted by biggest lift.'],
                                ] as [$t, $d])
                                    <div class="flex gap-4">
                                        <span aria-hidden="true" class="mt-1 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        </span>
                                        <div>
                                            <dt class="text-sm font-semibold">{{ $t }}</dt>
                                            <dd class="mt-1 text-sm leading-6 text-slate-600">{{ $d }}</dd>
                                        </div>
                                    </div>
                                @endforeach
                            </dl>
                        </div>

                        {{-- Visual: mock insight card --}}
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ([['Cannibalizations', '14', 'text-amber-600'], ['Striking distance', '27', 'text-indigo-600'], ['Index fails', '3', 'text-red-600'], ['Content decay', '8', 'text-slate-700'], ['Audit vs traffic', 'View', 'text-rose-600'], ['Backlink impact', 'View', 'text-emerald-600']] as [$lbl, $val, $color])
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $lbl }}</p>
                                        <p class="mt-1 text-xl font-bold tabular-nums {{ $color }}">{{ $val }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold text-slate-700">Striking-distance keywords</p>
                                <div class="mt-3 space-y-1.5 text-[11px]">
                                    @foreach ([['best seo tools', '12.4', '1,840', '0.9%'], ['on-page seo checklist', '9.1', '1,120', '1.1%'], ['seo for saas', '14.3', '890', '0.4%']] as [$q, $pos, $impr, $ctr])
                                        <div class="flex items-center justify-between gap-3 rounded bg-white px-2 py-1.5 ring-1 ring-slate-200">
                                            <span class="truncate font-medium text-slate-800">{{ $q }}</span>
                                            <span class="flex shrink-0 items-center gap-3 tabular-nums text-slate-500">
                                                <span>#{{ $pos }}</span>
                                                <span>{{ $impr }} impr</span>
                                                <span>{{ $ctr }}</span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Rank tracking --}}
            <section id="rank-tracking" class="bg-white py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                        {{-- Visual first on desktop --}}
                        <div class="order-last lg:order-first">
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">"best seo tools"</p>
                                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">#4 → #2</span>
                                </div>
                                {{-- Mini line chart --}}
                                <svg viewBox="0 0 320 90" class="mt-4 h-24 w-full" aria-hidden="true">
                                    <path d="M0 60 L40 55 L80 58 L120 45 L160 40 L200 32 L240 28 L280 22 L320 18" fill="none" stroke="#6366f1" stroke-width="2" />
                                    <path d="M0 60 L40 55 L80 58 L120 45 L160 40 L200 32 L240 28 L280 22 L320 18 L320 90 L0 90 Z" fill="#6366f1" fill-opacity="0.12" />
                                </svg>
                                <div class="mt-2 flex items-center justify-between text-[10px] text-slate-400">
                                    <span>90 days ago</span>
                                    <span>Today</span>
                                </div>
                                {{-- Clicks overlay --}}
                                <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-slate-500">Search clicks overlay</p>
                                <svg viewBox="0 0 320 60" class="mt-2 h-14 w-full" aria-hidden="true">
                                    <path d="M0 50 L40 48 L80 45 L120 35 L160 30 L200 20 L240 15 L280 10 L320 6 L320 60 L0 60 Z" fill="#10b981" fill-opacity="0.15" />
                                    <path d="M0 50 L40 48 L80 45 L120 35 L160 30 L200 20 L240 15 L280 10 L320 6" fill="none" stroke="#10b981" stroke-width="1.5" />
                                </svg>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase text-amber-700">SERP risk</span>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase text-slate-700">answerBox</span>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase text-slate-700">peopleAlsoAsk</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">Rank tracking</p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">See rankings and the clicks they actually earn</h2>
                            <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">
                                Most trackers show you what position you're in. EBQ also overlays the GSC clicks for that exact query — so you'll know when a rank move stops producing traffic.
                            </p>
                            <ul class="mt-8 space-y-4 text-sm text-slate-700">
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Device + country + language</strong> targeting — desktop/mobile/tablet, 30+ countries, custom city-level location.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Competitor positions</strong> captured on every check — track up to any domains you care about.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">SERP-feature risk flags</strong> — automatic badges when a competitor holds the featured snippet, knowledge panel, or top-stories carousel.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">People Also Ask + Related searches</strong> captured on every snapshot for content ideation.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Custom intervals</strong> per keyword (hourly to daily) and on-demand forced re-checks.</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Anomaly alerts --}}
            <section id="alerts" class="bg-slate-50 py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">Anomaly alerts</p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Know within hours when something breaks</h2>
                            <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">
                                Our detector compares yesterday against a 28-day baseline on clicks, sessions, and average tracked-keyword position. Two gates — relative drop AND z-score ≥ 2σ — stop the noise.
                            </p>
                            <ul class="mt-8 space-y-4 text-sm text-slate-700">
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-rose-500"></span><span><strong class="text-slate-900">Per-metric diagnosis</strong> — every alert shows current value, baseline mean, stddev, and % change so you can triage instantly.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-rose-500"></span><span><strong class="text-slate-900">24-hour deduplication</strong> — one alert per anomaly, not one per metric per check.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-rose-500"></span><span><strong class="text-slate-900">All report recipients</strong> are notified — stakeholders see drops before your Monday standup.</span></li>
                            </ul>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-900 p-5 font-mono text-xs shadow-xl">
                            <div class="flex items-center gap-2 border-b border-white/10 pb-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                                <span class="ml-2 text-[11px] text-slate-400">EBQ Alert: Traffic anomaly on example.com</span>
                            </div>
                            <div class="mt-3 space-y-2 text-slate-200">
                                <p>An unusual drop was detected for <span class="text-white">example.com</span> on 2026-04-20.</p>
                                <p class="text-amber-300">Search clicks: 212 vs baseline 844.5 (-74.9%, z=-3.2)</p>
                                <p class="text-amber-300">Sessions: 480 vs baseline 1,610 (-70.2%, z=-2.8)</p>
                                <p class="text-slate-400">Review traffic sources and keyword changes to investigate.</p>
                                <div class="pt-2"><span class="inline-block rounded bg-white px-3 py-1 text-[10px] font-semibold text-slate-900">Open EBQ →</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Page audits --}}
            <section id="audits" class="bg-white py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                        <div class="order-last lg:order-first">
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ([
                                    ['Performance', '42', 'red'],
                                    ['LCP', '4.8s', 'red'],
                                    ['CLS', '0.21', 'amber'],
                                    ['TBT', '610ms', 'red'],
                                    ['FCP', '2.1s', 'amber'],
                                    ['Speed Index', '5.2s', 'red'],
                                ] as [$lbl, $val, $tone])
                                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $lbl }}</p>
                                        <p @class([
                                            'mt-2 text-2xl font-bold tabular-nums',
                                            'text-red-600' => $tone === 'red',
                                            'text-amber-600' => $tone === 'amber',
                                        ])>{{ $val }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Top technical findings</p>
                                <ul class="mt-3 space-y-1.5 text-xs text-slate-700">
                                    <li class="flex items-start gap-2"><span class="mt-0.5 text-red-600">●</span><span>Render-blocking CSS (3 files, 180 KB)</span></li>
                                    <li class="flex items-start gap-2"><span class="mt-0.5 text-amber-600">●</span><span>Missing alt text on 7 images</span></li>
                                    <li class="flex items-start gap-2"><span class="mt-0.5 text-amber-600">●</span><span>Missing canonical tag</span></li>
                                    <li class="flex items-start gap-2"><span class="mt-0.5 text-slate-600">●</span><span>Content: 642 words · reading grade 9.3</span></li>
                                </ul>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">Page audits</p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Core Web Vitals + content + keywords in one pass</h2>
                            <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">
                                On-demand audits pair mobile and desktop Core Web Vitals with a deep HTML analyzer and a keyword-strategy review tailored to the page's target query.
                            </p>
                            <ul class="mt-8 space-y-4 text-sm text-slate-700">
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Full CWV</strong> — LCP, CLS, TBT, FCP, TTFB, Speed Index — both strategies.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">SEO signals</strong> — metadata, headings, canonical, schema, robots, hreflang, alt coverage, internal/external link health.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Content analysis</strong> — word count, reading grade, top keywords, locale detection, SERP-sample comparison.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Recommendation engine</strong> — prioritized action list per page, exportable as HTML/PDF.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Google Indexing API</strong> — request re-indexing directly from the page detail view.</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Backlinks --}}
            <section id="backlinks" class="bg-slate-50 py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">Backlinks</p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Prove which links actually moved the needle</h2>
                            <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">
                                Upload, verify, and measure. EBQ tells you which target pages lifted and by how much — data your outreach team needs to justify the next campaign.
                            </p>
                            <ul class="mt-8 space-y-4 text-sm text-slate-700">
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500"></span><span><strong class="text-slate-900">Bulk spreadsheet import</strong> or manual entry. Track DA, spam score, anchor, rel, and tracked-date.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500"></span><span><strong class="text-slate-900">Live verification</strong> — crawls referring pages to confirm the link is still live with correct anchor and rel attributes.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500"></span><span><strong class="text-slate-900">Pre/post impact analysis</strong> — 28-day click delta for every target page, sorted by biggest lift.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500"></span><span><strong class="text-slate-900">Filters</strong> — DA range, spam range, dofollow vs nofollow, anchor text, date range, audit status.</span></li>
                            </ul>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
                                <span>Backlink impact · last 28d</span>
                                <span>sorted by Δ clicks</span>
                            </div>
                            <table class="mt-3 min-w-full text-left text-[11px]">
                                <thead class="border-b border-slate-200 text-[10px] uppercase tracking-wider text-slate-500">
                                    <tr><th class="py-2 pr-2 font-semibold">Target page</th><th class="py-2 pr-2 text-right font-semibold">Links</th><th class="py-2 pr-2 text-right font-semibold">DA</th><th class="py-2 text-right font-semibold">Δ clicks</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ([['/pricing', 3, 58, '+412'], ['/blog/saas-seo', 7, 49, '+186'], ['/features', 2, 61, '+94'], ['/product/x', 4, 41, '-22']] as [$p, $n, $da, $delta])
                                        <tr>
                                            <td class="py-2 pr-2 font-medium text-slate-800">{{ $p }}</td>
                                            <td class="py-2 pr-2 text-right tabular-nums">{{ $n }}</td>
                                            <td class="py-2 pr-2 text-right tabular-nums">{{ $da }}</td>
                                            <td @class(['py-2 text-right tabular-nums font-semibold', 'text-emerald-600' => str_starts_with($delta, '+'), 'text-red-600' => str_starts_with($delta, '-')])>{{ $delta }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Reporting --}}
            <section id="reporting" class="bg-white py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                        <div class="order-last lg:order-first">
                            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
                                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                                    <p class="text-sm font-semibold">EBQ Weekly Report — example.com</p>
                                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Apr 13–19</span>
                                </div>
                                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                                    @foreach ([['Users', '8.4k', '+12%'], ['Clicks', '3.1k', '+8%'], ['Avg pos', '14.2', '-0.6']] as [$l, $v, $d])
                                        <div class="rounded-lg bg-slate-50 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $l }}</p>
                                            <p class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ $v }}</p>
                                            <p class="text-[10px] font-semibold text-emerald-600">{{ $d }}</p>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-4 rounded-lg bg-indigo-50 p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-600">Action Insights</p>
                                    <ul class="mt-2 space-y-1 text-[11px] text-slate-700">
                                        <li>• 5 striking-distance keywords — top CTR miss: "on-page seo checklist" (pos 9.1, 1.1% CTR)</li>
                                        <li>• 3 pages cannibalizing — "saas seo guide" across /blog and /guides</li>
                                        <li>• 1 indexing fail with traffic — /product/x earned 120 impr this week</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">Reporting</p>
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Executive-ready reports, zero manual rework</h2>
                            <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">
                                Daily, weekly, or monthly. Every report includes a YoY period comparison, top gainers/losers, traffic-source concentration, and the top-5 actionable insights — no pivot-table archaeology.
                            </p>
                            <ul class="mt-8 space-y-4 text-sm text-slate-700">
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Multi-recipient</strong> — per-website list, clients and stakeholders each see their own copy.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Custom date ranges</strong> with in-app preview before sending.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Scheduled daily auto-send</strong> at 08:00 in the website's timezone.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Appended Action Insights</strong> — top-5 cannibalization, striking-distance, and indexing-fails right inside the email.</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {{-- WordPress plugin --}}
            <section id="wordpress" class="bg-slate-50 py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                        <div>
                            <p class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700">WordPress plugin · Beta</p>
                            <h2 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">Ship insights where editors already work</h2>
                            <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">
                                The EBQ plugin drops into any WordPress site and surfaces cross-signal insights inside Gutenberg, the post list, and the WP dashboard. No credentials in the browser — a challenge-response verification flow mints a scoped API token.
                            </p>
                            <ul class="mt-8 space-y-4 text-sm text-slate-700">
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Gutenberg sidebar</strong> — rank chip, 90-day clicks sparkline, cannibalization warning, striking-distance flag, latest audit score per post.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Posts-list column</strong> — 30-day clicks, avg position, and cannibalized/tracked badges in the WP admin list.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Dashboard widget</strong> — four insight-count cards, each deep-linking to the matching EBQ Reports tab.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">Per-website scoped tokens</strong> — Sanctum polymorphic tokens. A leaked token can only read its own site.</span></li>
                                <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span><strong class="text-slate-900">One-click connect</strong> — plugin redirects to EBQ, user picks a website, EBQ bounces the token back. Standard OAuth redirect flow, state-nonce protected.</span></li>
                            </ul>
                            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">Start free to get the plugin</a>
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Sign in</a>
                            </div>
                            <p class="mt-3 text-xs text-slate-500">Requires WordPress 6.0+, PHP 8.1+, and an EBQ account. MIT-licensed source included.</p>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
                                <div class="flex items-center gap-2 border-b border-slate-200 pb-3 text-xs font-semibold text-slate-500">
                                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    <span class="ml-2">Gutenberg · EBQ SEO sidebar</span>
                                </div>
                                <div class="mt-4 space-y-3 text-xs">
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Search performance · 30d</p>
                                        <div class="mt-2 grid grid-cols-4 gap-2">
                                            @foreach ([['Clicks', '1,284'], ['Impr', '21.4k'], ['Pos', '6.4'], ['CTR', '6.0%']] as [$l, $v])
                                                <div class="rounded bg-white px-2 py-1.5 ring-1 ring-slate-200 text-center">
                                                    <span class="block text-[9px] uppercase text-slate-400">{{ $l }}</span>
                                                    <span class="block tabular-nums font-bold text-slate-900">{{ $v }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Rank tracking</p>
                                        <div class="mt-1 flex items-center gap-2"><span class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">#4</span><span class="text-emerald-700 text-[10px]">▲ 2</span><span class="text-[10px] text-slate-500">"best seo tools"</span></div>
                                    </div>
                                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700">Cannibalization</p>
                                        <p class="mt-1 text-[11px] text-amber-900">"best seo tools" is split with <span class="font-semibold">/blog/seo-tools-guide</span>.</p>
                                    </div>
                                    <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Striking distance</p>
                                        <p class="mt-1 text-[11px] text-indigo-900">3 queries at positions 5–20 with below-curve CTR.</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Dashboard widget mock --}}
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
                                <p class="text-xs font-semibold text-slate-500">WordPress dashboard widget</p>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    @foreach ([['Cannibalizations', '14', 'text-amber-600'], ['Striking distance', '27', 'text-indigo-600'], ['Index fails', '3', 'text-red-600'], ['Content decay', '8', 'text-slate-700']] as [$l, $v, $c])
                                        <div class="rounded-lg border border-slate-200 px-3 py-2">
                                            <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-500">{{ $l }}</p>
                                            <p class="mt-1 text-xl font-bold tabular-nums {{ $c }}">{{ $v }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Install steps --}}
                    <div class="mt-16">
                        <h3 class="text-xl font-semibold tracking-tight">Install in under sixty seconds</h3>
                        <ol class="mt-6 grid gap-4 lg:grid-cols-3">
                            @foreach ([
                                ['1', 'Upload + activate', 'Download the ZIP, upload via Plugins → Add New → Upload, activate.'],
                                ['2', 'Click Connect to EBQ', 'In WP: Settings → EBQ SEO → Connect. Log in to EBQ, pick which website to link, approve.'],
                                ['3', 'Done', 'EBQ bounces the token back automatically. Insights appear in Gutenberg, the post list, and the WP dashboard.'],
                            ] as [$n, $title, $body])
                                <li class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">{{ $n }}</p>
                                    <h4 class="mt-3 text-sm font-semibold">{{ $title }}</h4>
                                    <p class="mt-1 text-xs leading-5 text-slate-600">{{ $body }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </section>

            {{-- Team --}}
            <section id="team" class="bg-white py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">Team &amp; permissions</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Collaborate without giving away the keys</h2>
                    </div>
                    <div class="mt-12 grid gap-5 sm:grid-cols-3">
                        @foreach ([
                            ['Roles', 'Owner, Admin, Member — with per-feature toggle access for Members so clients see only what they need.'],
                            ['Invitations', 'Email invite flow with signed tokens. On signup, every pending invite for that email auto-accepts — onboarding takes one click.'],
                            ['Per-website scope', 'Access is always scoped to a specific website. Switch contexts from the top bar without losing state.'],
                        ] as [$t, $d])
                            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                <h3 class="text-base font-semibold">{{ $t }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $d }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Integrations --}}
            <section id="integrations" class="bg-slate-50 py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">Integrations</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Connected to the signals that matter</h2>
                        <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">OAuth-authenticated, synced daily, no spreadsheets.</p>
                    </div>
                    <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            ['Google Search Console', 'Clicks, impressions, position, CTR across query × page × device × country — daily, with configurable keyword lookback windows.'],
                            ['Google Analytics 4', 'Users, sessions, bounce rate — with source/medium attribution for the report dashboard.'],
                            ['Google Indexing API', 'Per-page verdict, coverage state, last-crawl timestamps. Submit re-index requests from the UI.'],
                            ['Core Web Vitals', 'Full CWV + performance score on mobile and desktop, piped straight into page audits.'],
                            ['Live SERP data', 'SERP-accurate rank tracking with device, country, language, depth, and SERP-feature extraction.'],
                            ['Email (SMTP / Postmark / Resend)', 'Any Laravel-supported mailer for growth reports, invitations, and anomaly alerts.'],
                        ] as [$t, $d])
                            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                <h3 class="text-base font-semibold">{{ $t }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $d }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- CTA --}}
            <section class="bg-slate-950 py-20 text-white sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="rounded-2xl border border-white/15 bg-slate-900 p-10 text-center">
                        <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Run SEO with a cleaner system</h2>
                        <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-100 sm:text-lg">
                            14-day free trial. Connect Search Console and Analytics in under ten minutes.
                        </p>
                        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">Create account</a>
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-md border border-white/25 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">Sign in</a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/10 bg-slate-950">
            <div class="mx-auto grid max-w-7xl gap-10 px-6 py-12 text-sm text-slate-200 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
                <div>
                    <a href="{{ route('landing') }}" class="flex items-center gap-3" aria-label="EBQ home">
                        <span aria-hidden="true" class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-cyan-400 text-xs font-bold text-white ring-1 ring-white/25">EBQ</span>
                        <span class="text-sm font-semibold uppercase tracking-[0.2em] text-white">EBQ</span>
                    </a>
                    <p class="mt-3 text-slate-400">SEO workspace for teams that ship.</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Product</p>
                    <ul class="mt-3 space-y-2">
                        <li><a class="hover:text-indigo-200" href="{{ route('features') }}">Features</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('pricing') }}">Pricing</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('landing') }}#wordpress">WordPress plugin</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Company</p>
                    <ul class="mt-3 space-y-2">
                        <li><a class="hover:text-indigo-200" href="mailto:hello@ebq.io">Contact</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('landing') }}#faq">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Legal</p>
                    <ul class="mt-3 space-y-2">
                        <li><a class="hover:text-indigo-200" href="{{ route('terms-conditions') }}">Terms &amp; Conditions</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('privacy-policy') }}">Privacy Policy</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('refund-policy') }}">Refund Policy</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/5">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-6 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                    <p>&copy; {{ date('Y') }} EBQ. All rights reserved.</p>
                    <p>Built for modern SEO teams.</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
