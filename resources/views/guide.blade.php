<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">

    @include('partials.favicon-links')

    <title>Feature Guide — Every metric, every column | EBQ</title>
    <meta name="description" content="The complete reference for EBQ. What every column means, how every number is calculated, and what action to take on it.">
    <link rel="canonical" href="{{ url('/guide') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="EBQ Feature Guide — every metric explained">
    <meta property="og:description" content="Drill down on every column, every badge, every chart inside EBQ. Written for customers, not engineers.">
    <meta property="og:url" content="{{ url('/guide') }}">
    <meta property="og:site_name" content="EBQ">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @if (! app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        /* Highlight the current-section link in the sticky TOC as the user scrolls. */
        .toc-link[aria-current="true"] { color: #4f46e5; font-weight: 600; }
        .toc-link[aria-current="true"]::before { background-color: #4f46e5; }
    </style>
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-800 antialiased selection:bg-indigo-500/30 selection:text-white">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-950">Skip to content</a>

    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b border-slate-900/10 bg-slate-950/95 text-slate-50 backdrop-blur">
        <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3" aria-label="EBQ home">
                <span aria-hidden="true" class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-cyan-400 text-xs font-bold text-white ring-1 ring-white/25">EBQ</span>
                <span class="text-sm font-semibold uppercase tracking-[0.2em] text-white">EBQ</span>
            </a>

            <nav aria-label="Primary" class="hidden items-center gap-8 text-sm font-medium text-slate-100 md:flex">
                <a href="{{ route('features') }}" class="transition hover:text-indigo-200">Features</a>
                <a href="{{ route('guide') }}" class="text-white transition hover:text-indigo-200" aria-current="page">Guide</a>
                <a href="{{ route('landing') }}#workflow" class="transition hover:text-indigo-200">Workflow</a>
                <a href="{{ route('landing') }}#results" class="transition hover:text-indigo-200">Results</a>
                <a href="{{ route('landing') }}#faq" class="transition hover:text-indigo-200">FAQ</a>
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('login') }}" class="hidden rounded-md px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 sm:inline-flex">Sign in</a>
                <a href="{{ route('register') }}" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">Start free</a>
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-slate-950 text-white">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-[28rem] bg-[radial-gradient(circle_at_20%_0,rgba(99,102,241,0.28),transparent_45%),radial-gradient(circle_at_80%_0,rgba(14,165,233,0.22),transparent_40%)]"></div>
        <div class="relative mx-auto max-w-5xl px-6 pb-16 pt-14 text-center lg:px-8 lg:pb-24 lg:pt-20">
            <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-indigo-200">
                <a href="{{ route('landing') }}" class="hover:text-white">Home</a>
                <span class="mx-2 text-indigo-300/60">›</span>
                <span>Guide</span>
            </p>
            <h1 class="mt-4 text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl">
                Every metric. Every column. Every action.
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-8 text-slate-100 sm:text-lg">
                Written for customers, not engineers. Each feature explains what it measures, how the number is calculated, and what to do when you see it.
            </p>
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="#data-sources" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">Start reading ↓</a>
                <a href="{{ route('features') }}" class="inline-flex items-center justify-center rounded-md border border-white/25 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">See marketing overview</a>
            </div>
        </div>
    </section>

    {{-- Main two-column layout: sticky TOC + content --}}
    <main id="main" class="mx-auto max-w-7xl px-6 py-16 lg:flex lg:gap-12 lg:px-8 lg:py-24">
        {{-- Sticky table of contents --}}
        <aside class="hidden lg:block lg:w-64 lg:shrink-0">
            <nav aria-label="On this page" class="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto pr-2 text-sm">
                <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">On this page</p>
                <ol class="space-y-1.5 border-l border-slate-200">
                    @foreach ([
                        ['#data-sources', 'Data sources'],
                        ['#dashboard', 'Dashboard'],
                        ['#insights-panel', 'Reports → Insights'],
                        ['#growth-reports', 'Growth report builder'],
                        ['#pages', 'Pages'],
                        ['#keywords', 'Keywords'],
                        ['#rank-tracking', 'Rank tracking'],
                        ['#page-audits', 'Page audits'],
                        ['#keyword-metrics', 'Keyword metrics'],
                        ['#country-filter', 'Country filter'],
                        ['#glossary', 'Glossary'],
                    ] as [$href, $label])
                        <li>
                            <a href="{{ $href }}" class="toc-link -ml-px block border-l-2 border-transparent pl-4 py-1 text-slate-600 transition hover:border-indigo-400 hover:text-indigo-700">{{ $label }}</a>
                        </li>
                    @endforeach
                </ol>
            </nav>
        </aside>

        {{-- Mobile TOC (accordion) --}}
        <details class="mb-10 rounded-xl border border-slate-200 bg-white p-4 lg:hidden">
            <summary class="flex cursor-pointer items-center justify-between text-sm font-semibold text-slate-900">
                <span>On this page</span>
                <svg class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </summary>
            <ul class="mt-3 space-y-1.5 text-sm">
                @foreach ([
                    ['#data-sources', 'Data sources'],
                    ['#dashboard', 'Dashboard'],
                    ['#insights-panel', 'Reports → Insights'],
                    ['#growth-reports', 'Growth report builder'],
                    ['#pages', 'Pages'],
                    ['#keywords', 'Keywords'],
                    ['#rank-tracking', 'Rank tracking'],
                    ['#page-audits', 'Page audits'],
                    ['#keyword-metrics', 'Keyword metrics'],
                    ['#country-filter', 'Country filter'],
                    ['#glossary', 'Glossary'],
                ] as [$href, $label])
                    <li><a href="{{ $href }}" class="block py-1 text-slate-600 hover:text-indigo-700">{{ $label }}</a></li>
                @endforeach
            </ul>
        </details>

        {{-- Content --}}
        <article class="prose prose-slate max-w-none prose-headings:scroll-mt-24 prose-h2:mb-4 prose-h2:mt-12 prose-h2:text-3xl prose-h2:font-semibold prose-h2:text-slate-900 prose-h3:mt-8 prose-h3:text-xl prose-h3:font-semibold prose-h3:text-slate-900 prose-p:leading-7 prose-p:text-slate-700 prose-a:text-indigo-600 prose-a:no-underline hover:prose-a:underline prose-strong:text-slate-900 prose-code:rounded prose-code:bg-slate-100 prose-code:px-1 prose-code:py-0.5 prose-code:text-[0.9em] prose-code:font-medium prose-code:text-slate-800 prose-code:before:content-[''] prose-code:after:content-[''] lg:flex-1">

            {{-- ============ Data sources ============ --}}
            <section id="data-sources">
                <h2>Data sources</h2>
                <p>Every number in EBQ traces back to one of five live sources. Hover any cell inside the app and you'll see which source it came from.</p>

                <div class="not-prose mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['Google Search Console', 'Queries, pages, clicks, impressions, CTR, position, country, device.', 'Nightly sync', 'bg-blue-50 border-blue-200 text-blue-900'],
                        ['Google Analytics 4', 'Users, sessions, source, bounce rate — the behavior layer.', 'Nightly sync', 'bg-amber-50 border-amber-200 text-amber-900'],
                        ['Google URL Inspection', 'Per-URL index verdict, coverage state, last crawl date.', 'Nightly + on-demand', 'bg-rose-50 border-rose-200 text-rose-900'],
                        ['Serper.dev (SERP API)', 'Organic rankings for tracked keywords, SERP features, competitor URLs.', 'Your chosen cadence (12h default)', 'bg-violet-50 border-violet-200 text-violet-900'],
                        ['Keywords Everywhere', 'Monthly search volume, CPC, competition, 12-month trend.', 'Cached 30 days per keyword', 'bg-emerald-50 border-emerald-200 text-emerald-900'],
                        ['EBQ Lighthouse', 'Core Web Vitals (LCP, CLS, performance score) for every audited URL.', 'On audit run', 'bg-sky-50 border-sky-200 text-sky-900'],
                    ] as [$name, $what, $cadence, $tone])
                        <div class="rounded-xl border {{ $tone }} p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider opacity-70">{{ $cadence }}</p>
                            <h3 class="mt-2 text-base font-semibold">{{ $name }}</h3>
                            <p class="mt-1 text-sm leading-6 opacity-90">{{ $what }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ============ Dashboard ============ --}}
            <section id="dashboard">
                <h2>Dashboard</h2>
                <p>The 30-second glance view. Every card is live against yesterday's data unless noted.</p>

                <h3 id="kpi-cards">KPI cards</h3>
                <p>The top row. Each card compares <strong>last 30 days vs. the previous 30 days</strong> for one metric.</p>

                {{-- Visual mockup of KPI cards --}}
                <div class="not-prose my-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Users (30d)', '12,847', '+18%', 'up', 'GA4'],
                        ['Sessions (30d)', '19,204', '+22%', 'up', 'GA4'],
                        ['Clicks (30d)', '8,932', '+7%', 'up', 'GSC'],
                        ['Impressions (30d)', '412k', '−3%', 'down', 'GSC'],
                    ] as [$label, $val, $delta, $dir, $src])
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-3xl font-bold tracking-tight text-slate-900">{{ $val }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $dir === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $delta }}</span>
                            </div>
                            <p class="mt-2 text-[10px] text-slate-400">via {{ $src }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="not-prose my-6 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                    <p class="text-sm text-indigo-900"><strong class="font-semibold">What to do:</strong> If clicks drop but impressions rise, you're ranking for more queries but converting fewer — check CTR and avg position in a growth report. If both drop together, check indexing status or run an audit.</p>
                </div>

                <h3 id="ppc-banner">PPC-equivalent banner</h3>
                <p>Indigo strip above the insight cards:</p>

                <div class="not-prose my-4 flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50/60 px-4 py-2 text-xs text-indigo-900">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <span>Your organic traffic is worth <strong>$4,230/month</strong> in PPC equivalent <span class="text-indigo-600/70">· based on 187 priced queries</span></span>
                </div>

                <p><strong>Formula:</strong> For every GSC query in the last 30 days where we have Keywords Everywhere data, we compute <code>(impressions × CTR for that position) × CPC</code>, then sum.</p>
                <p><strong>Why it's useful:</strong> Turns organic SEO into a number a finance team cares about — "if we turned this off, we'd need this much Google Ads budget to replace it."</p>
                <p><strong>Hidden when:</strong> Fewer than 10 queries have priced data (sample too small to be meaningful).</p>

                <h3 id="insight-cards">Action insights (insight cards)</h3>
                <p>Five cards that each count one type of issue. Click any card to jump to the matching <em>Reports → Insights</em> tab.</p>

                <div class="not-prose my-6 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Card</th>
                                <th class="px-3 py-2 font-semibold">Number shown</th>
                                <th class="px-3 py-2 font-semibold">What it means</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ([
                                ['Cannibalizations', 'amber', 'Queries where 2+ of your pages compete', "You're splitting click-share. Pick one page to own the query."],
                                ['Striking distance', 'indigo', 'Queries at positions 5–20 with traffic', 'One push gets them on page 1.'],
                                ['Index fails w/ traffic', 'rose', 'URLs Google says "not indexed" but still get impressions', 'Urgent — Google knows them but won\'t rank them properly.'],
                                ['Content decay', 'slate', 'Pages with sustained ≥15% click drop', 'Either rankings slipped or the keyword demand is fading.'],
                                ['Quick wins', 'emerald', "Low-competition keywords you don't rank top-10 for", 'New content opportunities, scored by dollar upside.'],
                            ] as [$name, $tone, $number, $meaning])
                                <tr>
                                    <td class="px-3 py-3 align-top">
                                        <span class="rounded-full bg-{{ $tone }}-100 px-2 py-0.5 text-xs font-semibold text-{{ $tone }}-700">{{ $name }}</span>
                                    </td>
                                    <td class="px-3 py-3 align-top text-slate-700">{{ $number }}</td>
                                    <td class="px-3 py-3 align-top text-slate-700">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3 id="traffic-chart">Traffic chart</h3>
                <p>30-day clicks trend overlaid with impressions. The red band marks any day where the anomaly detector flagged a drop. Hover any point for the exact value.</p>

                <h3 id="top-countries">Top countries</h3>
                <p>Horizontal-bar list of the top 10 countries driving clicks, last 30 days vs. previous 30 days. Each row shows flag + country name, a bar visualizing share, clicks in the last 30 days, and percent change vs. prior period. Spot where your traffic concentrates — if 90% comes from one country, check hreflang and locale signals.</p>

                <h3 id="seasonal-peaks">Seasonal peaks ahead</h3>
                <p>Amber card that only renders when Keywords Everywhere flags keywords as <strong>seasonal</strong> AND their historical peak month is within the next 60 days.</p>

                {{-- Mockup --}}
                <div class="not-prose my-6 rounded-xl border border-amber-200 bg-amber-50/40 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">◐ Seasonal peaks ahead</p>
                    <p class="mt-0.5 text-[11px] text-amber-800/70">Refresh these pages now — historical search peaks arrive in the next 60 days.</p>
                    <ul class="mt-4 space-y-2.5 text-xs">
                        @foreach ([
                            ['wedding dj prices', 'June', '14,800/mo at peak', '2 mo away'],
                            ['fathers day gifts', 'June', '301,000/mo at peak', '2 mo away'],
                            ['summer camp ideas', 'May', '22,200/mo at peak', '1 mo away'],
                        ] as [$kw, $month, $vol, $away])
                            <li class="flex items-center justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold text-slate-900">{{ $kw }}</div>
                                    <div class="mt-0.5 text-[10px] text-slate-500">peaks in <span class="font-medium text-amber-700">{{ $month }}</span> · {{ $vol }}</div>
                                </div>
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ $away }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p><strong>What to do:</strong> Refresh or re-publish content for these keywords <em>before</em> the season lands — Google needs time to crawl and re-rank.</p>

                <h3 id="quick-wins-card">Quick wins card</h3>
                <p>Emerald card with the top 5 quick-wins. Each row links directly to a pre-filled custom audit for that keyword. Hidden when the radar has nothing to show.</p>

                <h3 id="country-filter-dashboard">Country filter</h3>
                <p>The "Country" dropdown next to the insight heading scopes every downstream number to just that country's GSC impressions. See <a href="#country-filter">Country filter</a> below for the full list of surfaces it affects.</p>
            </section>

            {{-- ============ Insights panel ============ --}}
            <section id="insights-panel">
                <h2>Reports → Insights</h2>
                <p>The "Insights" sub-tab on <code>/dashboard/reports</code> is the full drill-down for each action card. Seven tabs, one table per tab. All tabs respect the country filter.</p>

                {{-- Cannibalization --}}
                <h3 id="cannibalization-tab">Cannibalizations tab</h3>
                <p>A query is "cannibalizing" when <strong>two or more of your pages</strong> rank for it and neither dominates the clicks.</p>

                <div class="not-prose my-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Filters applied</p>
                    <ul class="mt-2 space-y-1 text-sm text-slate-700">
                        <li>• Last 28 days</li>
                        <li>• ≥ 100 total impressions across all competing pages</li>
                        <li>• Primary page captures &lt; 90% of clicks (otherwise it's effectively resolved)</li>
                    </ul>
                </div>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Column</th>
                                <th class="px-4 py-2.5 font-semibold">Meaning</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Query', 'The query Google is confused about.'],
                                ['Primary page', 'The page currently getting the most clicks.'],
                                ['Pages', 'How many of your pages compete on this query.'],
                                ['Clicks', 'Total clicks the query attracted across all competing pages (28d).'],
                                ['Impr.', 'Total impressions across all competing pages (28d).'],
                                ['At stake', 'Full-market dollar value at position 1 (volume × top-of-SERP CTR × CPC). What your weakest pages are bleeding away. Dash if Keywords Everywhere has no data yet.'],
                                ['Competing pages (share %)', 'All competing URLs with the click share each one captures. Amber % label = they should consolidate.'],
                            ] as [$col, $meaning])
                                <tr>
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                    <p class="text-sm text-indigo-900"><strong class="font-semibold">What to do:</strong> Pick the primary page. Redirect the others to it, or heavily de-optimize them for the query. Update internal links to point at the primary.</p>
                </div>

                {{-- Striking distance --}}
                <h3 id="striking-tab">Striking distance tab</h3>
                <p>Queries ranking <strong>positions 5–20</strong> with high impressions and below-curve CTR — the fastest SEO wins.</p>

                <div class="not-prose my-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Filters applied</p>
                    <ul class="mt-2 space-y-1 text-sm text-slate-700">
                        <li>• Last 28 days</li>
                        <li>• ≥ 200 impressions</li>
                        <li>• Avg position between 5 and 20 (inclusive)</li>
                    </ul>
                </div>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">Meaning</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Query', 'The query.'],
                                ['Position', 'Average position over the period (rounded to 1 decimal).'],
                                ['Impressions', 'Total impressions (28d).'],
                                ['Clicks', 'Total clicks (28d).'],
                                ['CTR', 'Click-through rate as a percentage.'],
                                ['Upside/mo', 'Projected <strong>extra monthly dollars</strong> if this query reached position 3. Uses volume × CTR-curve-delta × CPC. Rows with data sort first; rows pending fall back to a heuristic score.'],
                            ] as [$col, $meaning])
                                <tr>
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                    <p class="text-sm text-indigo-900"><strong class="font-semibold">What to do:</strong> One push (title, meta, H1, intent fix, one strong internal link, one backlink) moves page-2 results onto page 1. These are your highest-ROI SEO bets.</p>
                </div>

                {{-- Index fails --}}
                <h3 id="index-fails-tab">Index fails with traffic tab</h3>
                <p>Pages Google's URL Inspection API flags as <strong>not PASS</strong> but which still got impressions in the last 14 days.</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">Meaning</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Page', 'The URL.'],
                                ['Verdict', "Google's own word — <code>PASS</code>, <code>FAIL</code>, etc."],
                                ['Coverage', 'Detailed reason — <em>"Crawled - currently not indexed"</em>, <em>"Discovered"</em>, etc.'],
                                ['Clicks (14d)', 'Clicks earned despite the indexing issue.'],
                                ['Impr. (14d)', 'Impressions earned despite the indexing issue.'],
                                ['Last crawl', 'When Google last visited this URL.'],
                            ] as [$col, $meaning])
                                <tr>
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-sm text-rose-900"><strong class="font-semibold">What to do:</strong> If impressions are present, Google <em>knows</em> the page exists. The block is trust/quality, not discoverability. Common fixes: boost internal links, improve content quality, request re-indexing after changes.</p>
                </div>

                {{-- Content decay --}}
                <h3 id="decay-tab">Content decay tab</h3>
                <p>Pages showing <strong>sustained click decline</strong> over the last 28 days vs. the prior 28 days.</p>

                <div class="not-prose my-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Filters applied</p>
                    <ul class="mt-2 space-y-1 text-sm text-slate-700">
                        <li>• ≥ 100 current impressions (filters out noise)</li>
                        <li>• ≥ 15% drop in clicks 28d-over-28d</li>
                    </ul>
                </div>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">Meaning</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Page', 'The URL. <span class="rounded-full bg-amber-100 px-1.5 py-px text-[9px] font-bold text-amber-700">market decline</span> pill appears when the decay is driven by falling keyword demand, not your page losing rank.'],
                                ['Clicks (28d)', 'Current-period clicks.'],
                                ['Prev 28d', 'Previous-period clicks.'],
                                ['∆ 28d', 'Percent change — the bigger the red number, the worse.'],
                                ['YoY', 'Same 28 days vs. a year ago. Dash if we don\'t yet have 13 months of history.'],
                                ['Verdict', 'Google indexing verdict. A non-PASS here means the decay is being masked by de-indexing.'],
                            ] as [$col, $meaning])
                                <tr>
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm text-amber-900"><strong class="font-semibold">The <code>market decline</code> pill:</strong> When ≥2 of the page's top-3 queries are themselves trending down in Keywords Everywhere's 12-month data, the decay is not your fault — the topic is fading. Don't waste hours rewriting; monitor and plan next-quarter content instead.</p>
                </div>

                {{-- Quick wins --}}
                <h3 id="quick-wins-tab">Quick wins tab</h3>
                <p>Low-competition keywords with real search volume where your site either doesn't rank or ranks outside the top 10. <strong>Scored by the dollar upside of reaching position 3.</strong></p>

                <div class="not-prose my-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Filters applied</p>
                    <ul class="mt-2 space-y-1 text-sm text-slate-700">
                        <li>• Keywords Everywhere volume ≥ 500/month</li>
                        <li>• Competition score ≤ 0.4 (where 1.0 = max)</li>
                        <li>• Site either has no GSC match OR best position &gt; 10 in the last 90 days</li>
                    </ul>
                </div>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">Meaning</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Keyword', 'The query to target.'],
                                ['Volume/mo', 'Keywords Everywhere monthly search volume (global).'],
                                ['Comp.', 'Competition score as a percentage (0–100). Lower = easier.'],
                                ['Current pos', 'Your best observed position in the last 90 days, or "unranked" if we\'ve never shown up.'],
                                ['Upside/mo', 'Projected dollar value if this keyword reached position 3.'],
                                ['Action', 'Either <em>"Audit current page"</em> (we know which page ranks) or <em>"Start new audit"</em> (unranked) — both deep-link to the custom-audit form with the keyword pre-filled.'],
                            ] as [$col, $meaning])
                                <tr>
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-sm text-emerald-900"><strong class="font-semibold">What to do:</strong> These are the clearest greenfield opportunities. Start a custom audit from the action link — it'll tell you exactly what to add to the page (or what to put in a new one).</p>
                </div>

                {{-- Audit vs traffic --}}
                <h3 id="audit-traffic-tab">Audit vs traffic tab</h3>
                <p>Pages with <strong>weak Core Web Vitals but high GSC impressions</strong> — technical debt measurably costing traffic.</p>

                <div class="not-prose my-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Filters applied</p>
                    <ul class="mt-2 space-y-1 text-sm text-slate-700">
                        <li>• Audit has been run</li>
                        <li>• Worst-of-mobile/desktop performance score &lt; 70</li>
                        <li>• ≥ 100 GSC impressions in the last 28 days</li>
                    </ul>
                </div>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">Meaning</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Page', 'The URL.'],
                                ['Mobile score', 'Performance 0–100 (red &lt; 50, amber 50–89, green ≥ 90).'],
                                ['Desktop score', 'Performance 0–100.'],
                                ['LCP (ms)', 'Largest Contentful Paint on mobile.'],
                                ['CLS', 'Cumulative Layout Shift on mobile.'],
                                ['Impressions', 'GSC impressions (28d).'],
                                ['Clicks', 'GSC clicks (28d).'],
                                ['Avg pos', 'Average position.'],
                                ['Audited at', 'When we last audited the page.'],
                            ] as [$col, $meaning])
                                <tr>
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Backlink --}}
                <h3 id="backlink-tab">Backlink impact tab</h3>
                <p>Shows click change <strong>before and after</strong> each backlink was discovered. Answers "did this link actually move the needle?"</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">Meaning</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Target page', 'The internal page receiving the link.'],
                                ['Referring domain', 'The domain linking to you.'],
                                ['Discovered', 'When the backlink appeared.'],
                                ['Clicks before', 'Average daily clicks 14 days before the link.'],
                                ['Clicks after', 'Average daily clicks 14 days after.'],
                                ['∆', 'Percent change — green positive, red negative.'],
                            ] as [$col, $meaning])
                                <tr>
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- ============ Growth reports ============ --}}
            <section id="growth-reports">
                <h2>Reports → Growth report builder</h2>
                <p>Build a shareable HTML/email report for any date range. Four report types: Daily, Weekly, Monthly, Custom. Previews inline, sends to every person on the website's recipient list when you click Email (rate-limited to 5/hour/user).</p>

                <h3>What's in the report</h3>
                <div class="not-prose my-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['Google Analytics', 'Users, Sessions, Bounce rate + top sources with per-source ∆. Top gainers/losers. Sessions-per-user ratio. Top-3 source concentration (single-channel reliance).'],
                        ['Google Search Console', 'Clicks, Impressions, Position, CTR + PPC-equivalent line. Top queries, top pages, devices, countries, position buckets, striking-distance opportunities.'],
                        ['Backlinks', 'New, lost, and total referring domains in the period.'],
                        ['Indexing', 'Indexed / not-indexed counts + list of any pages Google flagged.'],
                    ] as [$title, $body])
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h4 class="text-base font-semibold text-slate-900">{{ $title }}</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $body }}</p>
                        </div>
                    @endforeach
                </div>
                <p>All Search Console sections respect the country filter.</p>
            </section>

            {{-- ============ Pages table ============ --}}
            <section id="pages">
                <h2>Pages</h2>
                <p><code>/pages</code> — one row per unique URL with Search Console aggregates. Filters: URL search, "Only failing w/ traffic" checkbox, country.</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">Meaning</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Page', 'URL (clickable → per-page detail).'],
                                ['Market', 'Detected locale (hreflang + content-language + page-content heuristic).'],
                                ['Clicks', 'Sum of GSC clicks in the window configured under Settings → Reports (default 30d).'],
                                ['Impressions', 'Sum of GSC impressions in the same window.'],
                                ['Avg CTR', 'Ratio.'],
                                ['Avg Position', 'Average across all queries this URL ranked for.'],
                                ['Google Indexing Status', 'PASS / FAIL / Pending + coverage reason on hover.'],
                            ] as [$col, $meaning])
                                <tr>
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- ============ Keywords table ============ --}}
            <section id="keywords">
                <h2>Keywords</h2>
                <p><code>/keywords</code> — one row per unique query. Aggregated or by-date view. Cannibalized / tracked pills pre-flag queries of interest.</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">Meaning</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Keyword', 'The query.'],
                                ['Clicks', 'Clicks in the window.'],
                                ['Impressions', 'Impressions in the window.'],
                                ['CTR', 'Click-through rate.'],
                                ['Position', 'Average position, color-coded: green ≤ 3, blue 4–10, amber 11–20, grey 21+.'],
                                ['Volume', 'Monthly search volume from Keywords Everywhere. Trend arrow: <span class="font-bold text-emerald-600">↑</span> rising, <span class="font-bold text-rose-600">↓</span> falling, <span class="font-bold text-amber-600">◐</span> seasonal. Hover for last-updated + CPC.'],
                                ['Value/mo', 'Projected monthly dollar value at your current average position. <code>volume × CTR(position) × CPC</code>'],
                            ] as [$col, $meaning])
                                <tr>
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                    <p class="text-sm text-indigo-900"><strong class="font-semibold">What to do:</strong> Sort by Value/mo descending to find your biggest commercial keywords. Click a cannibalized pill to jump to the fix list.</p>
                </div>
            </section>

            {{-- ============ Rank tracking ============ --}}
            <section id="rank-tracking">
                <h2>Rank tracking</h2>
                <p>Dedicated rank-tracker for keywords you want to watch at a specific cadence (vs. GSC which only shows what Google happens to surface).</p>

                <h3>Rank tracker table</h3>
                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">Meaning</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Keyword', 'Click → keyword detail view. Badges: <code>search_type</code>, <span class="rounded bg-slate-200 px-1 text-[10px] font-semibold">Paused</span>, <span class="rounded bg-rose-100 px-1 text-[10px] font-semibold text-rose-700">Failed</span>, <span class="rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-700">SERP risk</span>, <span class="rounded bg-rose-100 px-1 text-[10px] font-semibold text-rose-700">lost feature</span>, plus your own tags.'],
                                ['Target', 'The domain you track. Optional target URL shown underneath in green if set — the row also shows the URL Google actually ranked.'],
                                ['Market', 'Country badge + language + device + location (if set).'],
                                ['Position', 'Current rank. Pill colors: green ≤ 3, blue 4–10, amber 11–20, grey 21+. Dash = unranked.'],
                                ['∆', 'Δ vs. last check. <span class="text-emerald-600 font-bold">▲</span> improvement, <span class="text-rose-600 font-bold">▼</span> decline.'],
                                ['Best', 'Best-ever position since you started tracking.'],
                                ['GSC (30d)', 'Side-by-side with Serper-measured position. What Google Search Console recorded for the same keyword in the last 30 days. Differences reveal personalization, locale, or CTR-under-curve issues.'],
                                ['Volume', 'Keywords Everywhere monthly search volume + trend arrow (↑ ↓ ◐). CPC + competition underneath. Dash while first fetch is pending.'],
                                ['Value/mo', 'Projected monthly value at current position. Hover for the formula breakdown.'],
                                ['Last check', 'When we last checked + when the next check runs. "Pending first check" if queued.'],
                                ['Actions', '<strong>View</strong> (detail), <strong>Re-check</strong> (force immediate), Pause/Resume, Delete.'],
                            ] as [$col, $meaning])
                                <tr>
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3>SERP feature risk</h3>
                <p>Automatic flag on each row:</p>
                <div class="not-prose my-6 space-y-3">
                    <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <span class="rounded bg-amber-100 px-1.5 py-px text-[10px] font-bold text-amber-700">SERP risk</span>
                        <p class="text-sm text-amber-900">Google is showing a SERP feature (AI overview, featured snippet, video carousel) and you don't own the top result — features pull clicks away from organic.</p>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 p-4">
                        <span class="rounded bg-rose-100 px-1.5 py-px text-[10px] font-bold text-rose-700">lost feature</span>
                        <p class="text-sm text-rose-900">You used to own a SERP feature (e.g., a featured snippet) and Google removed it — investigate in the last 7–14 days of page or competitor changes.</p>
                    </div>
                </div>
            </section>

            {{-- ============ Page audits ============ --}}
            <section id="page-audits">
                <h2>Page audits</h2>

                <h3 id="custom-audit">Custom audit tool</h3>
                <p><code>/custom-audit</code> — runs a deep on-page audit for a specific URL + target keyword.</p>

                <ol class="ml-6 list-decimal text-slate-700">
                    <li>Enter the page URL and target keyword.</li>
                    <li>Click <strong>Run audit</strong>. EBQ fetches the page's <code>&lt;head&gt;</code> to detect language/locale and suggests the Google SERP country to benchmark against.</li>
                    <li>Confirm or override the SERP country.</li>
                    <li>Click <strong>Run audit</strong> again. The audit queues in the background.</li>
                    <li>The "Recent custom audits" list polls itself. When complete, click the row to open the full report.</li>
                </ol>
                <p class="mt-4"><strong>Dedupe:</strong> if there's already an active audit for the same URL, EBQ won't let you queue a second one — saves credits. <strong>Rate limit:</strong> 8 audits per user per 2 minutes.</p>

                <h3 id="audit-report-sections">Every section of the audit report</h3>
                <p>The audit report is a single long card with numbered sections. Every section is collapsible, and the section index at the top is a clickable nav.</p>

                {{-- Score summary mockup --}}
                <div class="not-prose my-6 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-5">
                    <div class="flex items-start gap-4">
                        <div class="relative h-16 w-16 shrink-0">
                            <svg class="h-16 w-16 -rotate-90" viewBox="0 0 64 64">
                                <circle cx="32" cy="32" r="28" fill="none" stroke="currentColor" stroke-width="6" class="text-slate-200" />
                                <circle cx="32" cy="32" r="28" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-dasharray="175.93" stroke-dashoffset="50" class="text-amber-500" />
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-lg font-bold text-slate-900">72</span>
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-slate-500">score</span>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="flex items-center gap-2 text-base font-bold text-slate-900">Page Audit Report <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-amber-200">Needs attention</span></h4>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span class="rounded-md bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700">2 Critical</span>
                                <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">4 Warning</span>
                                <span class="rounded-md bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-800">1 SERP gap</span>
                                <span class="rounded-md bg-sky-100 px-2 py-0.5 text-[10px] font-semibold text-sky-700">3 Info</span>
                                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">6 Good</span>
                            </div>
                        </div>
                    </div>
                </div>

                <p><strong>Scoring formula:</strong> <code>100 − (critical × 15) − (warning × 6) − (serp_gap × 5) − (info × 2)</code>. Labels: Healthy (≥ 85), Needs attention (65–84), Critical (&lt; 65).</p>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">1. Recommendations</h4>
                <p>The core value of the audit — each item is one concrete action, tagged by severity.</p>

                <div class="not-prose my-4 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['Critical', 'rose', 'Blocks indexing, breaks the page, or leaks serious ranking signal. Fix immediately.'],
                        ['Warning', 'amber', 'Standard SEO issue — title too long, duplicate H1. Fix this week.'],
                        ['SERP gap', 'violet', "Competitors rank for sub-topics you don't cover. Write content."],
                        ['Info', 'sky', 'Heads-up — not broken, could be better.'],
                        ['Good', 'emerald', "Something you're doing well — keep it."],
                    ] as [$name, $tone, $meaning])
                        <div class="rounded-lg border border-{{ $tone }}-200 bg-{{ $tone }}-50 p-3">
                            <span class="inline-flex rounded-full bg-{{ $tone }}-100 px-2 py-0.5 text-[10px] font-bold uppercase text-{{ $tone }}-700">{{ $name }}</span>
                            <p class="mt-2 text-sm text-{{ $tone }}-900">{{ $meaning }}</p>
                        </div>
                    @endforeach
                </div>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">2. Core Web Vitals</h4>
                <p>Side-by-side mobile + desktop. Thresholds:</p>
                <div class="not-prose my-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Metric</th><th class="px-4 py-2.5 font-semibold">Good</th><th class="px-4 py-2.5 font-semibold">Needs work</th><th class="px-4 py-2.5 font-semibold">Poor</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr><td class="px-4 py-3 font-semibold text-slate-900">Performance score</td><td class="px-4 py-3 text-emerald-700">≥ 90</td><td class="px-4 py-3 text-amber-700">50–89</td><td class="px-4 py-3 text-rose-700">&lt; 50</td></tr>
                            <tr><td class="px-4 py-3 font-semibold text-slate-900">LCP (ms)</td><td class="px-4 py-3 text-emerald-700">≤ 2500</td><td class="px-4 py-3 text-amber-700">≤ 4000</td><td class="px-4 py-3 text-rose-700">&gt; 4000</td></tr>
                            <tr><td class="px-4 py-3 font-semibold text-slate-900">CLS</td><td class="px-4 py-3 text-emerald-700">≤ 0.1</td><td class="px-4 py-3 text-amber-700">≤ 0.25</td><td class="px-4 py-3 text-rose-700">&gt; 0.25</td></tr>
                        </tbody>
                    </table>
                </div>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">3. Technical</h4>
                <p>HTTP status, robots.txt, meta robots, canonical, XML sitemap, hreflang, SSL, page size, response time. Problems here become <code>critical</code> or <code>warning</code> recommendations.</p>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">4. SERP readability benchmark</h4>
                <p>Runs your target keyword through Google (via Serper.dev), fetches the top 5 organic results, extracts readability + structure metrics, and compares yours to the average. Shows your organic rank as a hero stat (first page / page 2+), a competitor readability table with Flesch scores + word/image/heading/link counts, and a gap table showing exactly how much content you need to add to match competitor averages.</p>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">5. Keyword Strategy</h4>
                <p>Powered by GSC + Keywords Everywhere. Answers "Are you targeting the right keywords on this page?"</p>
                <ul class="ml-6 list-disc text-slate-700">
                    <li><strong>Primary keyword</strong> — from GSC (highest-click) or the target keyword you set.</li>
                    <li><strong>Power placement</strong> — checks if it appears in Title, H1, Meta description.</li>
                    <li><strong>Coverage score</strong> (0–100) — how many top GSC queries are present in the body.</li>
                    <li><strong>Intent mix</strong> — commercial / informational / navigational blend. Spots pages targeting confused intents.</li>
                    <li><strong>Accidental authority</strong> — queries you rank for but never explicitly targeted. Opportunities for an H2 or FAQ.</li>
                    <li><strong>Missing queries</strong> — queries you rank for but whose text isn't in the body.</li>
                    <li><strong>Target keywords from Search Console</strong> table — with <strong>Vol</strong> column (Keywords Everywhere volume) + In-body YES/NO pill. Sort by Vol to prioritize rewrites.</li>
                </ul>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">6. Traffic by country (GSC)</h4>
                <p>Collapsible panel showing where the page earns clicks in the last 30 days.</p>

                <div class="not-prose my-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex cursor-pointer items-center gap-3 bg-slate-50 px-5 py-3">
                        <svg class="h-3.5 w-3.5 rotate-90 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-5 items-center gap-1 rounded-full bg-indigo-600 px-2 text-[10px] font-semibold uppercase tracking-wider text-white">GSC traffic</span>
                                <span class="text-sm font-semibold text-slate-900">By country</span>
                                <span class="text-[11px] text-slate-400">· last 30 days</span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-slate-500">3 markets · <span class="font-semibold text-slate-700">12,450</span> clicks · top: 🇺🇸 <span class="font-semibold text-slate-700">United States</span> <span class="text-slate-400">(42%)</span></p>
                        </div>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ([
                            ['🇺🇸', 'United States', 'US', 42, 5240, 4500],
                            ['🇮🇳', 'India', 'IN', 28, 2820, 2100],
                            ['🇬🇧', 'United Kingdom', 'GB', 30, 4390, 3800],
                        ] as $i => [$flag, $name, $code, $share, $bar, $clicks])
                            <li class="flex items-center gap-3 px-5 py-2.5 text-xs">
                                <span class="w-4 shrink-0 text-right text-[10px] font-mono text-slate-400">{{ $i + 1 }}</span>
                                <span class="flex min-w-0 flex-1 items-center gap-2">
                                    <span class="text-sm">{{ $flag }}</span>
                                    <span class="font-medium text-slate-800">{{ $name }}</span>
                                    <span class="text-[10px] font-mono text-slate-400">{{ $code }}</span>
                                </span>
                                <div class="hidden w-40 items-center gap-2 sm:flex">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200">
                                        <div class="h-full rounded-full {{ $i === 0 ? 'bg-indigo-600' : 'bg-indigo-400/80' }}" style="width: {{ $bar/5240*100 }}%"></div>
                                    </div>
                                </div>
                                <span class="w-12 shrink-0 text-right font-semibold text-slate-600">{{ $share }}%</span>
                                <span class="w-16 shrink-0 text-right text-slate-900">{{ number_format($clicks) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p><strong>Why it's here:</strong> A CWV audit on a page with 70% of its traffic from USA mobile tells a different story than the same audit on a page with evenly-distributed traffic. This panel contextualizes severity.</p>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">7. Metadata</h4>
                <p>Title (50–60 chars ideal), Meta description (120–158 chars ideal), canonical URL, Open Graph tags, Twitter cards, Schema.org types, hreflang alternates.</p>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">8. Content &amp; Structure</h4>
                <p>H1 count (exactly 1), H2/H3/H4+ counts, word count, Flesch reading ease, paragraph count, internal vs. external link ratio.</p>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">9. Image &amp; Link Analysis</h4>
                <p>Images: total, missing alt attributes, oversized (&gt; 500KB), next-gen format adoption. Links: internal/external counts, <strong>broken links</strong> (auto-opens when present, red-tinted), full link outline.</p>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">10. Technical Performance</h4>
                <p>Raw Lighthouse output — render-blocking resources, unused JS, etc.</p>

                <h4 class="mt-8 text-lg font-semibold text-slate-900">11. Advanced Data</h4>
                <p>Everything else the crawl captured — HTTP headers, structured data validation, canonical chains, redirect chains, robots.txt excerpt. Power-user reference.</p>

                <h3 id="audit-download">Downloading and emailing a report</h3>
                <p>Two buttons at the bottom of the audit page:</p>
                <ul class="ml-6 list-disc text-slate-700">
                    <li><strong>Download</strong> — renders to a standalone HTML file with a print-optimized stylesheet. Save, print, or convert to PDF.</li>
                    <li><strong>Email report</strong> — sends the HTML to any email address. 5 sends per user per 5 minutes.</li>
                </ul>
                <p>Both versions include every section with a static layout — no JavaScript, email-safe.</p>
            </section>

            {{-- ============ Keyword metrics ============ --}}
            <section id="keyword-metrics">
                <h2>Keyword metrics (Keywords Everywhere layer)</h2>
                <p>Every "Volume", "Value/mo", "CPC", "Competition", and trend arrow in the app reads from a cached Keywords Everywhere lookup.</p>

                <h3>What we fetch</h3>
                <div class="not-prose my-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['Search volume', 'Monthly Google searches, global aggregate.'],
                        ['CPC', "Average cost-per-click advertisers pay for this keyword on Google Ads."],
                        ['Competition', '0.0–1.0 bid competition score. Advertiser competition, not SEO difficulty — but the two correlate.'],
                        ['Trend (12-month)', "Array of last 12 months' search volume. Feeds trend arrow + seasonality detection."],
                    ] as [$name, $desc])
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h4 class="text-sm font-semibold text-slate-900">{{ $name }}</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>

                <h3>Trend classifications</h3>
                <div class="not-prose my-6 space-y-2">
                    <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-3">
                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-sm font-bold text-emerald-700">↑ rising</span>
                        <p class="text-sm text-emerald-900">Log-slope of last 6 months &gt; +0.08.</p>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-rose-200 bg-rose-50/50 p-3">
                        <span class="rounded bg-rose-100 px-2 py-0.5 text-sm font-bold text-rose-700">↓ falling</span>
                        <p class="text-sm text-rose-900">Log-slope of last 6 months &lt; −0.08.</p>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-3">
                        <span class="rounded bg-amber-100 px-2 py-0.5 text-sm font-bold text-amber-700">◐ seasonal</span>
                        <p class="text-sm text-amber-900">Coefficient of variation &gt; 0.6 across 12 months — recurring peaks.</p>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <span class="rounded bg-slate-200 px-2 py-0.5 text-sm font-bold text-slate-700">stable</span>
                        <p class="text-sm text-slate-700">We have trend data and it's flat.</p>
                    </div>
                </div>

                <h3>Value calculations (CTR curve)</h3>
                <p>Projected-value formulas use a Sistrix-style SERP click-through curve:</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Position</th><th class="px-4 py-2.5 font-semibold">CTR</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 tabular-nums">
                            @foreach ([
                                ['1', '28%'], ['2', '15%'], ['3', '11%'], ['4', '8%'], ['5', '7%'],
                                ['6', '5%'], ['7', '4%'], ['8', '3%'], ['9', '2.5%'], ['10', '2%'],
                                ['11–20', '1%'], ['21+', '0.5%'],
                            ] as [$pos, $ctr])
                                <tr>
                                    <td class="px-4 py-2 font-semibold text-slate-900">{{ $pos }}</td>
                                    <td class="px-4 py-2">{{ $ctr }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-sm text-slate-800">
                    <p>projected_monthly_clicks = volume × CTR(position)</p>
                    <p class="mt-2">projected_monthly_value = projected_monthly_clicks × CPC</p>
                    <p class="mt-2">upside_value = projected_value(3) − projected_value(current_position)</p>
                </div>

                <h3>How data gets cached</h3>
                <ul class="ml-6 list-disc text-slate-700">
                    <li><strong>Nightly GSC sync</strong> — queries with ≥ 100 impressions auto-queue for Keywords Everywhere lookup (skipping fresh rows).</li>
                    <li><strong>New tracked keyword</strong> — single global fetch on creation.</li>
                    <li><strong>Stale-while-revalidate</strong> — when any page renders and finds a row older than 30 days, it shows what we have and queues a background refresh.</li>
                </ul>
                <p>Each row stays fresh for 30 days. No re-billing on cached data.</p>
                <p><strong>Why rows show "—":</strong> Keywords Everywhere hasn't been queried for that specific keyword yet. Auto-fetch picks it up within ~24 hours on the next sync.</p>
            </section>

            {{-- ============ Country filter ============ --}}
            <section id="country-filter">
                <h2>Country filtering across the app</h2>
                <p>A single dropdown on the dashboard and Reports panel scopes every downstream number to one country. URL-persisted (shareable).</p>

                <div class="not-prose my-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-5">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-emerald-900">
                            <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            Affected
                        </h4>
                        <ul class="mt-3 space-y-1.5 text-sm text-emerald-900/80">
                            <li>• Dashboard insight counts</li>
                            <li>• PPC-equivalent banner</li>
                            <li>• Cannibalizations, Striking distance, Index fails, Content decay, Quick wins tabs</li>
                            <li>• Growth report builder (Search Console section)</li>
                            <li>• Pages + Keywords tables</li>
                            <li>• Rank tracker's GSC-join column</li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-5">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            Not affected
                        </h4>
                        <ul class="mt-3 space-y-1.5 text-sm text-slate-700">
                            <li>• Analytics (GA4 has no country dimension in our schema)</li>
                            <li>• Backlinks (third-party data)</li>
                            <li>• Audit vs traffic tab</li>
                            <li>• Audit reports themselves (per-URL, country-agnostic)</li>
                        </ul>
                    </div>
                </div>
                <p>Only countries where your site has recorded GSC impressions appear in the dropdown.</p>
            </section>

            {{-- ============ Glossary ============ --}}
            <section id="glossary">
                <h2>Glossary</h2>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Term</th><th class="px-4 py-2.5 font-semibold">Definition</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Addressable value', 'Full dollar value of a keyword at position 1 — <code>volume × top-CTR × CPC</code>. Cannibalization ceiling.'],
                                ['Cannibalization', 'When ≥ 2 of your pages compete for the same query, splitting click share.'],
                                ['Content decay', 'Sustained ≥ 15% click drop 28 days over prior 28 days.'],
                                ['Coverage score', '% of your top GSC queries that appear in the page body.'],
                                ['CPC', 'Cost-per-click — what advertisers bid for this query. Proxy for commercial intent.'],
                                ['CTR', 'Click-through rate — clicks ÷ impressions.'],
                                ['CTR curve', 'Industry-accepted CTR-by-position table (Sistrix rounded).'],
                                ['Custom audit', 'User-triggered audit of a specific URL + keyword, with SERP benchmark. Queues in background.'],
                                ['Decay reason', '<code>recoverable</code> (page lost rank) vs. <code>market_decline</code> (query itself falling in KE data).'],
                                ['Depth (rank tracker)', 'How many SERP positions to scan. 100 = finds rank even on page 10.'],
                                ['Impressions', 'Times your page appeared in a search result anyone saw.'],
                                ['Index verdict', "Google's word on indexability — <code>PASS</code>, <code>FAIL</code>, or specific reasons."],
                                ['LCP', 'Largest Contentful Paint. Core Web Vital — time to the main visual element.'],
                                ['PPC equivalent', "Ad spend you'd need on Google Ads to replicate organic traffic at current CPC rates."],
                                ['Primary keyword', "Highest-click GSC query for the page, or your override when queuing a custom audit."],
                                ['Projected value', 'Monthly dollar value at current rank — <code>volume × CTR(current) × CPC</code>.'],
                                ['Quick win', "Keyword with real volume, low competition, where your site doesn't rank top-10."],
                                ['Recommendation severity', 'critical / warning / SERP gap / info / good — audit-report pill colors.'],
                                ['SERP feature', 'Anything non-organic on a Google result page — AI overview, snippet, PAA, Knowledge Panel, etc.'],
                                ['Striking distance', "Query ranking 5–20 with real impressions — one push to page 1."],
                                ['Target keyword', "Keyword you specify when queuing a custom audit."],
                                ['Trend class', 'rising / falling / seasonal / stable / unknown — 12-month pattern.'],
                                ['Upside value', "Dollars gained if a keyword reaches position 3 — <code>projected(3) − projected(current)</code>."],
                                ['Volume', 'Monthly global search volume from Keywords Everywhere.'],
                                ['YoY', 'Year-over-year comparison.'],
                            ] as [$term, $def])
                                <tr>
                                    <td class="w-48 px-4 py-3 align-top font-semibold text-slate-900">{{ $term }}</td>
                                    <td class="px-4 py-3 align-top">{!! $def !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- CTA --}}
            <section class="not-prose mt-20 rounded-2xl bg-gradient-to-br from-indigo-600 to-indigo-800 p-8 text-center text-white sm:p-12">
                <h2 class="text-2xl font-bold sm:text-3xl">Ready to operate on real signal?</h2>
                <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-indigo-100 sm:text-base">
                    Every surface above is ready the moment you connect Search Console. Start free — no credit card.
                </p>
                <div class="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-indigo-700 transition hover:bg-slate-100">Start free trial</a>
                    <a href="{{ route('features') }}" class="inline-flex items-center justify-center rounded-md border border-white/30 bg-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/20">See marketing overview</a>
                </div>
            </section>
        </article>
    </main>

    {{-- Footer --}}
    <footer class="border-t border-slate-200 bg-white py-10">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 text-xs text-slate-500 lg:flex-row lg:px-8">
            <p>&copy; {{ date('Y') }} EBQ. All rights reserved.</p>
            <nav aria-label="Footer" class="flex items-center gap-5">
                <a href="{{ route('landing') }}" class="hover:text-slate-900">Home</a>
                <a href="{{ route('features') }}" class="hover:text-slate-900">Features</a>
                <a href="{{ route('guide') }}" class="hover:text-slate-900">Guide</a>
                <a href="{{ route('register') }}" class="hover:text-slate-900">Sign up</a>
            </nav>
        </div>
    </footer>

    {{-- Highlight the active TOC link as the user scrolls. --}}
    <script>
        (function () {
            const tocLinks = document.querySelectorAll('.toc-link');
            if (! tocLinks.length) return;

            const sections = [];
            tocLinks.forEach(link => {
                const id = link.getAttribute('href').slice(1);
                const el = document.getElementById(id);
                if (el) sections.push({ id, el, link });
            });

            if (! sections.length) return;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        sections.forEach(s => s.link.removeAttribute('aria-current'));
                        const active = sections.find(s => s.id === entry.target.id);
                        if (active) active.link.setAttribute('aria-current', 'true');
                    }
                });
            }, { rootMargin: '-20% 0px -70% 0px' });

            sections.forEach(s => observer.observe(s.el));
        })();
    </script>
</body>
</html>
