<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">

    @include('partials.favicon-links')

    <title>Product Guide — Every feature explained | EBQ</title>
    <meta name="description" content="A tour of every screen, every column, and every action inside EBQ — written for SEO teams who want to know what to do, not just what they're looking at.">
    <link rel="canonical" href="{{ url('/guide') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="EBQ Product Guide — every feature explained">
    <meta property="og:description" content="A tour of every screen, every column, and every action inside EBQ. Written for SEO teams.">
    <meta property="og:url" content="{{ url('/guide') }}">
    <meta property="og:site_name" content="EBQ">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @if (! app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        /* Sticky TOC active-link highlight as the user scrolls. */
        .toc-link[aria-current="true"] { color: #4f46e5; font-weight: 600; background-color: rgb(238 242 255 / 0.6); }
        .toc-link[aria-current="true"]::before { background-color: #4f46e5; }
        /* Section number chip */
        .section-num {
            display: inline-flex; align-items: center; justify-content: center;
            height: 2rem; width: 2rem; border-radius: 9999px;
            background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
            color: white; font-size: 0.8125rem; font-weight: 700;
            box-shadow: 0 2px 8px rgb(79 70 229 / 0.25);
        }
        /* Scroll-to-top button fade */
        .scroll-top {
            opacity: 0; pointer-events: none; transition: opacity 0.2s ease;
        }
        .scroll-top.visible { opacity: 1; pointer-events: auto; }
    </style>
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-800 antialiased selection:bg-indigo-500/30 selection:text-white">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-950">Skip to content</a>

    {{-- ============ Header ============ --}}
    <header class="sticky top-0 z-40 border-b border-slate-900/10 bg-slate-950/95 text-slate-50 backdrop-blur">
        <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3" aria-label="EBQ home">
                <img src="{{ asset('logo.png') }}" alt="" aria-hidden="true" width="36" height="36" class="h-9 w-9 rounded-lg object-cover ring-1 ring-white/25">
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

    {{-- ============ Hero ============ --}}
    <section class="relative overflow-hidden bg-slate-950 text-white">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-[34rem] bg-[radial-gradient(circle_at_20%_0,rgba(99,102,241,0.28),transparent_45%),radial-gradient(circle_at_80%_0,rgba(14,165,233,0.22),transparent_40%)]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_bottom,transparent_0%,rgba(2,6,23,0.6)_70%,#0f172a_100%)]"></div>

        <div class="relative mx-auto max-w-5xl px-6 pb-20 pt-14 text-center lg:px-8 lg:pb-28 lg:pt-20">
            <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-indigo-200">
                <a href="{{ route('landing') }}" class="hover:text-white">Home</a>
                <span class="mx-2 text-indigo-300/60">›</span>
                <span>Guide</span>
            </p>
            <h1 class="mt-5 text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl">
                The EBQ Product Guide
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-8 text-slate-100 sm:text-lg">
                A tour of every screen, every column, every action — written for SEO teams who want to know <em class="font-semibold not-italic text-white">what to do</em>, not just what they're looking at.
            </p>
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="#data-sources" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                    Start reading
                    <svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3"/></svg>
                </a>
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md border border-white/25 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">Try it free</a>
            </div>

            {{-- Stats ribbon --}}
            <dl class="mx-auto mt-14 grid max-w-3xl grid-cols-2 gap-6 text-left sm:grid-cols-4">
                @foreach ([
                    ['6', 'Data sources blended'],
                    ['7', 'Action-insight tabs'],
                    ['50+', 'Audit checks per page'],
                    ['Live', 'Against yesterday\'s data'],
                ] as [$stat, $label])
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-[0.18em] text-indigo-200">{{ $label }}</dt>
                        <dd class="mt-1 text-2xl font-bold text-white sm:text-3xl">{{ $stat }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ============ Main layout: sticky TOC + content ============ --}}
    <main id="main" class="mx-auto max-w-7xl px-6 py-16 lg:flex lg:gap-14 lg:px-8 lg:py-24">
        {{-- Desktop sticky TOC --}}
        <aside class="hidden lg:block lg:w-64 lg:shrink-0">
            <nav aria-label="On this page" class="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto pr-2 text-sm">
                <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">On this page</p>
                <ol class="space-y-0.5 border-l border-slate-200">
                    @foreach ([
                        ['#data-sources', 'Data sources'],
                        ['#dashboard', 'Dashboard'],
                        ['#insights-panel', 'Insights'],
                        ['#growth-reports', 'Growth reports'],
                        ['#pages', 'Pages'],
                        ['#keywords', 'Keywords'],
                        ['#rank-tracking', 'Rank tracking'],
                        ['#page-audits', 'Page audits'],
                        ['#keyword-metrics', 'Keyword intelligence'],
                        ['#country-filter', 'Country filter'],
                        ['#wordpress-plugin', 'WordPress plugin'],
                        ['#glossary', 'Glossary'],
                    ] as [$href, $label])
                        <li>
                            <a href="{{ $href }}" class="toc-link -ml-px block rounded-r border-l-2 border-transparent py-1.5 pl-4 pr-2 text-slate-600 transition hover:border-indigo-400 hover:bg-slate-100 hover:text-indigo-700">{{ $label }}</a>
                            @if ($href === '#wordpress-plugin')
                                <ul class="ml-4 mt-1 space-y-0.5 border-l border-slate-100 pl-2 text-[12px]">
                                    @foreach ([
                                        ['#wp-live-score', 'Live SEO score'],
                                        ['#wp-ai-rewrites', 'AI snippet rewrites'],
                                        ['#wp-content-brief', 'AI content brief'],
                                        ['#wp-entity-coverage', 'Entity coverage'],
                                        ['#wp-topical-gaps', 'Topical gaps'],
                                        ['#wp-redirects-ai', 'AI redirect matcher'],
                                        ['#wp-prospects', 'Backlink prospects'],
                                        ['#wp-serp-features', 'SERP features'],
                                        ['#wp-benchmarks', 'Network benchmarks'],
                                        ['#wp-topical-authority', 'Topical authority'],
                                        ['#wp-automatic', 'Automatic behaviors'],
                                        ['#wp-pro-vs-free', 'Free vs Pro'],
                                    ] as [$subHref, $subLabel])
                                        <li><a href="{{ $subHref }}" class="block rounded px-2 py-0.5 text-slate-500 transition hover:text-indigo-700">{{ $subLabel }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        </aside>

        {{-- Mobile TOC --}}
        <details class="mb-10 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:hidden">
            <summary class="flex cursor-pointer items-center justify-between text-sm font-semibold text-slate-900 [&::-webkit-details-marker]:hidden">
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    On this page
                </span>
                <svg class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </summary>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ([
                    ['#data-sources', 'Data sources'],
                    ['#dashboard', 'Dashboard'],
                    ['#insights-panel', 'Insights'],
                    ['#growth-reports', 'Growth reports'],
                    ['#pages', 'Pages'],
                    ['#keywords', 'Keywords'],
                    ['#rank-tracking', 'Rank tracking'],
                    ['#page-audits', 'Page audits'],
                    ['#keyword-metrics', 'Keyword intelligence'],
                    ['#country-filter', 'Country filter'],
                    ['#wordpress-plugin', 'WordPress plugin'],
                    ['#glossary', 'Glossary'],
                ] as [$href, $label])
                    <li><a href="{{ $href }}" class="block rounded px-2 py-1.5 text-slate-600 transition hover:bg-slate-50 hover:text-indigo-700">{{ $label }}</a></li>
                @endforeach
            </ul>
        </details>

        {{-- ============ Content ============ --}}
        <article class="prose prose-slate max-w-none prose-headings:scroll-mt-24 prose-h2:mb-0 prose-h2:mt-0 prose-h3:mt-10 prose-h3:text-xl prose-h3:font-semibold prose-h3:text-slate-900 prose-p:leading-7 prose-p:text-slate-700 prose-a:text-indigo-600 prose-a:no-underline hover:prose-a:underline prose-strong:text-slate-900 prose-code:rounded prose-code:bg-slate-100 prose-code:px-1.5 prose-code:py-0.5 prose-code:text-[0.85em] prose-code:font-medium prose-code:text-slate-800 prose-code:before:content-[''] prose-code:after:content-[''] prose-ul:my-4 prose-li:my-1 lg:flex-1">

            @php
                $sectionHeader = function (string $num, string $title, ?string $sub = null) {
                    $sub = $sub ? "<p class=\"mt-1 text-sm leading-6 text-slate-500\">{$sub}</p>" : '';
                    return "<div class=\"not-prose mb-10 flex items-start gap-4\"><span class=\"section-num shrink-0\">{$num}</span><div><h2 class=\"text-3xl font-semibold tracking-tight text-slate-900\">{$title}</h2>{$sub}</div></div>";
                };
            @endphp

            {{-- ============ 1. Data sources ============ --}}
            <section id="data-sources" class="scroll-mt-24">
                {!! $sectionHeader('1', 'Data sources', 'Every number in EBQ traces back to one of six live sources. Hover any cell inside the app to see which source it came from.') !!}

                <div class="not-prose grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['Google Search Console', 'Queries, pages, clicks, impressions, CTR, position — segmented by country and device.', 'Nightly sync', 'bg-blue-50 border-blue-200 text-blue-900', 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125'],
                        ['Google Analytics 4', 'Users, sessions, traffic sources, bounce rate — the behavior layer.', 'Nightly sync', 'bg-amber-50 border-amber-200 text-amber-900', 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z'],
                        ['Google URL Inspection', "Per-URL index verdict, coverage state, last crawl date — straight from Google.", 'Nightly + on demand', 'bg-rose-50 border-rose-200 text-rose-900', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['SERP API', 'Organic rankings for tracked keywords, SERP features, competitor URLs.', 'Your cadence (12h default)', 'bg-violet-50 border-violet-200 text-violet-900', 'M15.75 15.75l-2.489-2.489m0 0a3.375 3.375 0 10-4.773-4.773 3.375 3.375 0 004.774 4.774zM21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['Keyword intelligence', 'Monthly search volume, commercial value, competition, 12-month trend.', 'Cached per keyword', 'bg-emerald-50 border-emerald-200 text-emerald-900', 'M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z'],
                        ['EBQ Core Web Vitals', 'Mobile + desktop LCP, CLS, performance score for every audited URL.', 'On audit run', 'bg-sky-50 border-sky-200 text-sky-900', 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z'],
                    ] as [$name, $what, $cadence, $tone, $icon])
                        <div class="rounded-xl border {{ $tone }} p-5 transition hover:shadow-md">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/60">
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider opacity-70">{{ $cadence }}</p>
                                    <h3 class="text-base font-semibold">{{ $name }}</h3>
                                    <p class="mt-1 text-sm leading-6 opacity-90">{{ $what }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-6 text-sm text-slate-500">Nothing in the app is estimated. Every cell has a source — if we don't have data for a specific keyword or page yet, we say so with a dash, not a guess.</p>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 2. Dashboard ============ --}}
            <section id="dashboard" class="scroll-mt-24">
                {!! $sectionHeader('2', 'Dashboard', 'The 30-second glance view. Every card is live against yesterday\'s data.') !!}

                <h3 id="kpi-cards">KPI cards</h3>
                <p>The top row. Each card compares <strong>last 30 days vs. the previous 30 days</strong> for one metric.</p>

                <div class="not-prose my-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Users (30d)', '12,847', '+18%', 'up', 'GA4'],
                        ['Sessions (30d)', '19,204', '+22%', 'up', 'GA4'],
                        ['Clicks (30d)', '8,932', '+7%', 'up', 'GSC'],
                        ['Impressions (30d)', '412k', '−3%', 'down', 'GSC'],
                    ] as [$label, $val, $delta, $dir, $src])
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-3xl font-bold tracking-tight text-slate-900">{{ $val }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $dir === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $delta }}</span>
                            </div>
                            <p class="mt-2 text-[10px] text-slate-400">via {{ $src }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="not-prose my-6 rounded-xl border-l-4 border-indigo-400 bg-indigo-50/60 p-4">
                    <p class="text-sm text-indigo-900"><strong class="font-semibold">How to read them:</strong> If clicks drop but impressions rise, you're ranking for more queries but converting fewer — check CTR and avg position in a growth report. If both drop together, check indexing status or run an audit.</p>
                </div>

                <h3 id="ppc-banner">PPC-equivalent banner</h3>
                <p>An indigo strip above the insight cards translates your organic traffic into an ad-spend equivalent:</p>

                <div class="not-prose my-4 flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50/60 px-4 py-3 text-sm text-indigo-900">
                    <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Your organic traffic is worth approximately <strong class="font-semibold">$4,230/month</strong> in PPC equivalent <span class="text-indigo-600/70 text-xs">· based on 187 priced queries</span></span>
                </div>

                <p>Turns organic SEO into a number a finance team cares about — <em>"if we turned this off, we'd need this much Google Ads budget to replace it."</em> Hidden when we don't yet have enough priced queries to produce a meaningful estimate.</p>

                <h3 id="insight-cards">Action-insight cards</h3>
                <p>Five cards that each count one type of issue. Click any card to jump to the matching <em>Reports → Insights</em> tab and see the full list.</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Card</th>
                                <th class="px-4 py-2.5 font-semibold">What it counts</th>
                                <th class="px-4 py-2.5 font-semibold">Why you care</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ([
                                ['Cannibalizations', 'amber', 'Queries where two or more of your pages compete', "Click share splits. Pick one page to own the query."],
                                ['Striking distance', 'indigo', 'Queries just outside page 1', 'One push gets them onto page 1.'],
                                ['Index fails with traffic', 'rose', "Pages Google won't index properly that still earn impressions", "Urgent — Google knows the page but won't rank it."],
                                ['Content decay', 'slate', 'Pages losing clicks vs. the prior period', 'Either rankings slipped or the keyword demand is fading.'],
                                ['Quick wins', 'emerald', "Low-competition keywords you don't rank top-10 for", 'Greenfield opportunities, ranked by dollar upside.'],
                            ] as [$name, $tone, $number, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top">
                                        <span class="inline-flex rounded-full bg-{{ $tone }}-100 px-2.5 py-0.5 text-xs font-semibold text-{{ $tone }}-700">{{ $name }}</span>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-700">{{ $number }}</td>
                                    <td class="px-4 py-3 align-top text-slate-600">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3 id="traffic-chart">Traffic chart</h3>
                <p>30-day clicks overlaid with impressions. The red band marks any day where our anomaly detector flagged an unusual drop. Hover any point for exact numbers.</p>

                <h3 id="top-countries">Top countries</h3>
                <p>Horizontal-bar list of the top 10 countries driving clicks, last 30 days vs. previous 30 days. Each row shows flag, country name, visual share bar, absolute clicks, and delta. Spot where traffic concentrates — if 90% comes from one country, check hreflang and locale signals.</p>

                <h3 id="seasonal-peaks">Seasonal peaks ahead</h3>
                <p>Amber card that only renders when a keyword we track shows a recurring seasonal pattern AND its historical peak month is within the next 60 days.</p>

                <div class="not-prose my-6 rounded-xl border border-amber-200 bg-amber-50/40 p-5 shadow-sm">
                    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-700"><span>◐</span> Seasonal peaks ahead</p>
                    <p class="mt-1 text-[11px] text-amber-800/70">Refresh these pages now — historical search peaks arrive in the next 60 days.</p>
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

                <p>Refresh or re-publish content for these keywords <em>before</em> the season lands — Google needs time to crawl and re-rank.</p>

                <h3 id="quick-wins-card">Quick wins card</h3>
                <p>Emerald card with the top 5 quick-wins. Each row links directly to a pre-filled custom audit for that keyword. Hidden when the radar has nothing to show.</p>

                <h3 id="country-filter-dashboard">Country filter</h3>
                <p>A dropdown next to the insight heading scopes every downstream number to just that country's GSC impressions. See <a href="#country-filter">Country filter</a> for the full list of surfaces it affects.</p>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 3. Insights ============ --}}
            <section id="insights-panel" class="scroll-mt-24">
                {!! $sectionHeader('3', 'Reports → Insights', 'The full drill-down for each action card. Seven tabs, one purpose-built table per tab.') !!}

                <p>Every tab respects the country filter at the top of the panel (with two exceptions noted). Each tab follows the same rhythm: <em>what's in the list</em> → <em>columns</em> → <em>what to do next</em>.</p>

                {{-- Cannibalization --}}
                <h3 id="cannibalization-tab">Cannibalizations</h3>
                <p>A query is "cannibalizing" when two or more of your pages rank for it and neither dominates the clicks. We surface the ones that have enough volume to be worth fixing.</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">What it tells you</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Query', 'The query Google is confused about.'],
                                ['Primary page', 'The page currently capturing the most clicks.'],
                                ['Pages', 'How many of your pages compete on this query.'],
                                ['Clicks', 'Total clicks the query attracted across all competing pages in the last 28 days.'],
                                ['Impr.', 'Total impressions across all competing pages.'],
                                ['At stake', "Estimated monthly value of this query if a single page owned it. What the split is costing you. Dash if we don't have pricing data for it yet."],
                                ['Competing pages (share %)', 'All competing URLs with their click share. Amber % labels = strong candidates to consolidate.'],
                            ] as [$col, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border-l-4 border-indigo-400 bg-indigo-50/60 p-4">
                    <p class="text-sm text-indigo-900"><strong class="font-semibold">Action:</strong> Pick the primary page. Redirect the others to it, or heavily de-optimize them for the query. Update internal links to point at the primary.</p>
                </div>

                {{-- Striking distance --}}
                <h3 id="striking-tab">Striking distance</h3>
                <p>Queries ranking just outside page 1 with real impressions and below-curve CTR. The fastest SEO wins on your content calendar.</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">What it tells you</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Query', 'The query.'],
                                ['Position', 'Average position over the period.'],
                                ['Impressions', 'Total impressions in the last 28 days.'],
                                ['Clicks', 'Total clicks.'],
                                ['CTR', 'Click-through rate.'],
                                ['Upside/mo', 'Projected extra monthly dollars if this query reached the top of page 1. Priced rows sort first; rows still awaiting pricing data fall back to our internal priority score.'],
                            ] as [$col, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border-l-4 border-indigo-400 bg-indigo-50/60 p-4">
                    <p class="text-sm text-indigo-900"><strong class="font-semibold">Action:</strong> One push (title, meta, H1, intent fix, one internal link, one backlink) moves page-2 results onto page 1. Highest-ROI SEO bets live here.</p>
                </div>

                {{-- Index fails --}}
                <h3 id="index-fails-tab">Index fails with traffic</h3>
                <p>Pages Google's URL Inspection API flags as not-PASS but which still earned impressions recently. Google <em>knows</em> the page exists — something is blocking the rank.</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">What it tells you</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Page', 'The URL.'],
                                ['Verdict', "Google's own word — PASS, FAIL, or specific status."],
                                ['Coverage', 'Detailed reason — e.g., <em>"Crawled - currently not indexed"</em>.'],
                                ['Clicks (14d)', 'Clicks earned despite the indexing issue.'],
                                ['Impr. (14d)', 'Impressions earned despite the indexing issue.'],
                                ['Last crawl', 'When Google last visited this URL.'],
                            ] as [$col, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border-l-4 border-rose-400 bg-rose-50/70 p-4">
                    <p class="text-sm text-rose-900"><strong class="font-semibold">Action:</strong> The block is trust/quality, not discoverability. Common fixes: boost internal links, improve content quality, request re-indexing after changes.</p>
                </div>

                {{-- Content decay --}}
                <h3 id="decay-tab">Content decay</h3>
                <p>Pages losing a meaningful share of clicks period-over-period while still earning enough impressions to be worth rescuing.</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">What it tells you</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Page', 'The URL. A <span class="rounded-full bg-amber-100 px-1.5 py-px text-[9px] font-bold text-amber-700">market decline</span> pill appears when the decay is driven by falling keyword demand, not your page losing rank.'],
                                ['Clicks (28d)', 'Current-period clicks.'],
                                ['Prev 28d', 'Previous-period clicks.'],
                                ['∆ 28d', 'Percent change — the bigger the red number, the worse.'],
                                ['YoY', "Same 28 days vs. a year ago. Dash if we don't yet have 13 months of history."],
                                ['Verdict', 'Google indexing verdict. A non-PASS here means the decay is being masked by de-indexing.'],
                            ] as [$col, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border-l-4 border-amber-400 bg-amber-50/70 p-4">
                    <p class="text-sm text-amber-900"><strong class="font-semibold">The <code class="bg-amber-100/60">market decline</code> pill:</strong> When the page's top queries are themselves fading market-wide, the decay is the <em>topic</em>, not your page. Monitor and plan next-quarter content — don't waste hours rewriting.</p>
                </div>

                {{-- Quick wins --}}
                <h3 id="quick-wins-tab">Quick wins</h3>
                <p>Keywords with real volume, low competition, and where your site either doesn't rank or ranks outside the top 10 — scored by the dollar upside of reaching the top.</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">What it tells you</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Keyword', 'The query to target.'],
                                ['Volume/mo', 'Monthly search volume.'],
                                ['Comp.', 'Competition score as a percentage — lower is easier.'],
                                ['Current pos', "Your best observed position in the last 90 days, or \"unranked\" if we've never shown up."],
                                ['Upside/mo', 'Projected dollar value if this keyword reached the top.'],
                                ['Action', 'Deep-links to a custom audit with the keyword pre-filled. If we know which page ranks, it opens an audit for that page; otherwise it starts a fresh one.'],
                            ] as [$col, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border-l-4 border-emerald-400 bg-emerald-50/70 p-4">
                    <p class="text-sm text-emerald-900"><strong class="font-semibold">Action:</strong> Click the action link. The audit will tell you exactly what to add to the page — or what to put in a new one.</p>
                </div>

                {{-- Audit vs traffic --}}
                <h3 id="audit-traffic-tab">Audit vs traffic</h3>
                <p>Pages with weak Core Web Vitals but <em>high</em> GSC impressions — technical debt measurably costing traffic.</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">What it tells you</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Page', 'The URL.'],
                                ['Mobile score', 'Performance 0–100 (red, amber, green bands).'],
                                ['Desktop score', 'Performance 0–100.'],
                                ['LCP (ms)', 'Largest Contentful Paint on mobile.'],
                                ['CLS', 'Cumulative Layout Shift on mobile.'],
                                ['Impressions', 'GSC impressions in the last 28 days.'],
                                ['Clicks', 'GSC clicks.'],
                                ['Avg pos', 'Average position.'],
                                ['Audited at', 'When we last audited the page.'],
                            ] as [$col, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Backlink impact --}}
                <h3 id="backlink-tab">Backlink impact</h3>
                <p>Click change <strong>before vs. after</strong> each backlink appeared. Answers "did this link actually move the needle?"</p>

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">What it tells you</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Target page', 'The internal page receiving the link.'],
                                ['Referring domain', 'The domain linking to you.'],
                                ['Discovered', 'When the backlink appeared.'],
                                ['Clicks before', 'Average daily clicks before the link.'],
                                ['Clicks after', 'Average daily clicks after.'],
                                ['∆', 'Percent change — green positive, red negative.'],
                            ] as [$col, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 4. Growth reports ============ --}}
            <section id="growth-reports" class="scroll-mt-24">
                {!! $sectionHeader('4', 'Growth report builder', 'Build a shareable HTML report for any date range. Preview inline, send it to your recipient list.') !!}

                <p>Four presets — Daily, Weekly, Monthly, Custom — plus an override for any date range. Rate-limited to 5 sends per hour per user.</p>

                <h3 id="report-sections">What's inside</h3>
                <div class="not-prose my-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['Google Analytics', 'Users, Sessions, Bounce rate with per-period ∆. Top sources, top gainers/losers, sessions-per-user ratio, source concentration.', '#4f46e5'],
                        ['Google Search Console', 'Clicks, Impressions, Position, CTR + PPC-equivalent line. Top queries, top pages, devices, countries, position buckets, striking-distance opportunities.', '#06b6d4'],
                        ['Backlinks', 'New, lost, and total referring domains in the period.', '#10b981'],
                        ['Indexing', 'Indexed / not-indexed counts + list of any pages Google flagged.', '#f59e0b'],
                    ] as [$title, $body, $color])
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                            <span class="inline-block h-1 w-12 rounded-full" style="background-color: {{ $color }};"></span>
                            <h4 class="mt-3 text-base font-semibold text-slate-900">{{ $title }}</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $body }}</p>
                        </div>
                    @endforeach
                </div>

                <p>All Search Console sections respect the country filter.</p>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 5. Pages ============ --}}
            <section id="pages" class="scroll-mt-24">
                {!! $sectionHeader('5', 'Pages', 'One row per unique URL with Search Console aggregates. Filter by URL substring, by country, or to just the indexing-fails cohort.') !!}

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">What it tells you</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Page', 'URL (clickable → per-page detail).'],
                                ['Market', 'Detected locale (hreflang + content-language + page-content heuristic).'],
                                ['Clicks', 'GSC clicks in your configured window (default 30d).'],
                                ['Impressions', 'GSC impressions in the same window.'],
                                ['Avg CTR', 'Ratio.'],
                                ['Avg Position', 'Average across all queries this URL ranked for.'],
                                ['Google Indexing Status', 'PASS / FAIL / Pending + coverage reason on hover.'],
                            ] as [$col, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 6. Keywords ============ --}}
            <section id="keywords" class="scroll-mt-24">
                {!! $sectionHeader('6', 'Keywords', 'One row per unique query. Aggregated or by-date view. Cannibalized and tracked pills pre-flag queries of interest.') !!}

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">What it tells you</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Keyword', 'The query.'],
                                ['Clicks', 'Clicks in the window.'],
                                ['Impressions', 'Impressions in the window.'],
                                ['CTR', 'Click-through rate.'],
                                ['Position', 'Average position, color-coded from green (top) to grey (off page 2).'],
                                ['Volume', 'Monthly search volume. Trend arrow: <span class="font-bold text-emerald-600">↑</span> rising, <span class="font-bold text-rose-600">↓</span> falling, <span class="font-bold text-amber-600">◐</span> seasonal.'],
                                ['Value/mo', "Projected monthly dollar value at your current position."],
                            ] as [$col, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="not-prose my-6 rounded-xl border-l-4 border-indigo-400 bg-indigo-50/60 p-4">
                    <p class="text-sm text-indigo-900"><strong class="font-semibold">Tip:</strong> Sort by Value/mo descending to find your biggest commercial keywords. Click a cannibalized pill to jump to the fix list.</p>
                </div>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 7. Rank tracking ============ --}}
            <section id="rank-tracking" class="scroll-mt-24">
                {!! $sectionHeader('7', 'Rank tracking', 'Dedicated tracker for keywords you want to watch at a specific cadence.') !!}

                <h3>Columns</h3>
                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Column</th><th class="px-4 py-2.5 font-semibold">What it tells you</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Keyword', 'Click → keyword detail. Badges underneath flag search type, paused/failed state, SERP risk, lost features, and your tags.'],
                                ['Target', 'The domain you track; optional target URL shown beneath if set.'],
                                ['Market', 'Country + language + device + optional location.'],
                                ['Position', 'Current rank. Pills colored from green (top) to grey (off page 2).'],
                                ['∆', 'Change vs. last check. <span class="text-emerald-600 font-bold">▲</span> improvement, <span class="text-rose-600 font-bold">▼</span> decline.'],
                                ['Best', 'Best-ever position since you started tracking.'],
                                ['GSC (30d)', 'Side-by-side with our SERP-measured rank, this is what Search Console recorded for the same keyword. Differences reveal personalization, locale, or CTR issues.'],
                                ['Volume', 'Monthly search volume + trend arrow. CPC + competition underneath.'],
                                ['Value/mo', 'Projected monthly value at current position.'],
                                ['Last check', 'When we last checked + when the next check runs.'],
                                ['Actions', '<strong>View</strong> (detail), <strong>Re-check</strong>, Pause/Resume, Delete.'],
                            ] as [$col, $meaning])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $col }}</td>
                                    <td class="px-4 py-3 align-top">{!! $meaning !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3>SERP feature risk</h3>
                <p>Two automatic flags on each row:</p>
                <div class="not-prose my-6 space-y-3">
                    <div class="flex items-start gap-3 rounded-lg border-l-4 border-amber-400 bg-amber-50 p-4">
                        <span class="mt-0.5 rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">SERP risk</span>
                        <p class="text-sm text-amber-900">Google is showing a SERP feature (AI overview, featured snippet, video carousel) and you don't own the top slot. Features pull clicks away from organic.</p>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg border-l-4 border-rose-400 bg-rose-50 p-4">
                        <span class="mt-0.5 rounded bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">lost feature</span>
                        <p class="text-sm text-rose-900">You used to own a SERP feature (e.g., a snippet) and Google removed it. Investigate the last 7–14 days of page or competitor changes.</p>
                    </div>
                </div>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 8. Page audits ============ --}}
            <section id="page-audits" class="scroll-mt-24">
                {!! $sectionHeader('8', 'Page audits', 'Deep on-page audits that combine Core Web Vitals, on-page SEO, SERP benchmark, and competitor backlinks into a single report.') !!}

                <h3 id="custom-audit">Custom audit tool</h3>
                <p>Enter a page URL and target keyword. EBQ auto-detects the page's locale and suggests the right Google SERP country to benchmark against. Confirm and click run — audits queue in the background and update their row live. No refresh needed.</p>

                <div class="not-prose my-6 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['Dedupe', 'If an audit is already active for the same URL, we silently drop the second one so credits aren\'t wasted.'],
                        ['Rate limit', '8 audits per user per 2 minutes.'],
                        ['Locale detection', 'We read the page head and suggest the best SERP country so your benchmark is apples-to-apples.'],
                        ['Live status', "The recent-audits list polls itself. Queued → Running → Completed, with no reload."],
                    ] as [$title, $body])
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h4 class="text-sm font-semibold text-slate-900">{{ $title }}</h4>
                            <p class="mt-1.5 text-xs leading-5 text-slate-600">{{ $body }}</p>
                        </div>
                    @endforeach
                </div>

                <h3 id="audit-report-sections">Every section of the audit report</h3>
                <p>The audit is one long card, section-by-section. The index at the top lets you jump to any section.</p>

                {{-- Score summary mockup --}}
                <div class="not-prose my-6 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="relative h-16 w-16 shrink-0">
                            <svg class="h-16 w-16 -rotate-90" viewBox="0 0 64 64">
                                <circle cx="32" cy="32" r="28" fill="none" stroke="currentColor" stroke-width="6" class="text-slate-200"/>
                                <circle cx="32" cy="32" r="28" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-dasharray="175.93" stroke-dashoffset="50" class="text-amber-500"/>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-lg font-bold text-slate-900">72</span>
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-slate-500">score</span>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="flex flex-wrap items-center gap-2 text-base font-bold text-slate-900">
                                Page Audit Report
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-amber-200">Needs attention</span>
                            </h4>
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

                <p>A single score (0–100) with the label <em>Healthy</em>, <em>Needs attention</em>, or <em>Critical</em>. Severity pills underneath count what's wrong at a glance.</p>

                {{-- Section 1 --}}
                <h4 class="!mt-10 text-lg font-semibold text-slate-900">1. Recommendations</h4>
                <p>The core value of the audit — each item is one concrete action, tagged by severity.</p>

                <div class="not-prose my-6 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['Critical', 'rose', 'Blocks indexing, breaks the page, or leaks serious ranking signal. Fix today.'],
                        ['Warning', 'amber', 'Standard SEO issue — title too long, duplicate H1, etc. Fix this week.'],
                        ['SERP gap', 'violet', "Competitors rank for sub-topics you don't cover. Write content."],
                        ['Info', 'sky', 'Not broken, could be better.'],
                        ['Good', 'emerald', "Something you're doing well — keep it."],
                    ] as [$name, $tone, $meaning])
                        <div class="rounded-xl border border-{{ $tone }}-200 bg-{{ $tone }}-50 p-4">
                            <span class="inline-flex rounded-full bg-{{ $tone }}-100 px-2.5 py-0.5 text-[10px] font-bold uppercase text-{{ $tone }}-700">{{ $name }}</span>
                            <p class="mt-2 text-sm text-{{ $tone }}-900">{{ $meaning }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Section 2 --}}
                <h4 class="!mt-10 text-lg font-semibold text-slate-900">2. Core Web Vitals</h4>
                <p>Mobile + desktop performance score, LCP, CLS, and related load metrics — measured by our Core Web Vitals runner. Each number is color-coded against Google's public good/needs-improvement/poor thresholds.</p>

                {{-- Section 3 --}}
                <h4 class="!mt-10 text-lg font-semibold text-slate-900">3. Technical</h4>
                <p>HTTP status, robots.txt, meta robots, canonical, XML sitemap, hreflang, SSL, page size, response time. Issues here become <code>critical</code> or <code>warning</code> recommendations.</p>

                {{-- Section 4 --}}
                <h4 class="!mt-10 text-lg font-semibold text-slate-900">4. SERP readability benchmark</h4>
                <p>We query Google for your target keyword, fetch the top 5 organic results, and compare your page's content shape to the market. You'll see:</p>
                <ul>
                    <li>Your organic rank with a first-page/page-2+ badge.</li>
                    <li>Competitor readability table — Flesch score, word count, images, headings, and links for each.</li>
                    <li>A gap table showing exactly how much content (or how many headings/images) you need to add to match the competitor average.</li>
                </ul>
                <p>Each competitor row now also exposes a <strong>Top backlinks</strong> disclosure — click to see the referring domains linking to them, with authority score and anchor text. Cached per competitor, so repeat audits don't burn credits.</p>

                {{-- Section 5 --}}
                <h4 class="!mt-10 text-lg font-semibold text-slate-900">5. Keyword Strategy</h4>
                <p>Answers "Are you targeting the right keywords on this page?" Shows:</p>
                <ul>
                    <li><strong>Primary keyword</strong> — the query we consider most representative.</li>
                    <li><strong>Power placement</strong> — does it appear in Title, H1, Meta description?</li>
                    <li><strong>Coverage score</strong> — how much of your top GSC queries are in the page body.</li>
                    <li><strong>Intent mix</strong> — commercial / informational / navigational blend. Spots confused pages.</li>
                    <li><strong>Accidental authority</strong> — queries you rank for but never targeted. Easy wins.</li>
                    <li><strong>Missing queries</strong> — queries you rank for but whose text isn't in the body.</li>
                    <li><strong>Target keywords from Search Console</strong> — with monthly volume and "in body?" pill. Sort by volume to prioritize rewrites.</li>
                </ul>

                {{-- Section 6 — Traffic by country --}}
                <h4 class="!mt-10 text-lg font-semibold text-slate-900">6. Traffic by country</h4>
                <p>Collapsible panel showing where this URL earns clicks in the last 30 days.</p>

                <div class="not-prose my-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex cursor-pointer items-center gap-3 bg-slate-50 px-5 py-3">
                        <svg class="h-3.5 w-3.5 rotate-90 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
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

                <p>A Core Web Vitals audit on a page where 70% of traffic is USA-mobile tells a very different story than one with evenly-distributed traffic. This panel contextualizes every other score on the page.</p>

                {{-- Sections 7–11 --}}
                <h4 class="!mt-10 text-lg font-semibold text-slate-900">7. Metadata</h4>
                <p>Title length, meta description length, canonical URL, Open Graph tags, Twitter cards, Schema.org types, hreflang alternates. Problems become recommendations in Section 1.</p>

                <h4 class="!mt-10 text-lg font-semibold text-slate-900">8. Content &amp; Structure</h4>
                <p>H1 count, H2/H3/H4+ counts, word count, Flesch reading ease, paragraph count, internal vs. external link ratio.</p>

                <h4 class="!mt-10 text-lg font-semibold text-slate-900">9. Image &amp; Link Analysis</h4>
                <p>Images: total, missing alt attributes, oversized files, next-gen format adoption. Links: internal/external counts, broken links (auto-opens when present), full link outline.</p>

                <h4 class="!mt-10 text-lg font-semibold text-slate-900">10. Technical Performance</h4>
                <p>Raw performance output — render-blocking resources, unused JS, image-format opportunities.</p>

                <h4 class="!mt-10 text-lg font-semibold text-slate-900">11. Advanced Data</h4>
                <p>HTTP headers, structured data validation, canonical chains, redirect chains, robots.txt excerpt. Power-user reference.</p>

                <h3 id="audit-download">Download and email</h3>
                <div class="not-prose my-6 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <svg class="h-4 w-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            Download
                        </h4>
                        <p class="mt-1.5 text-xs leading-5 text-slate-600">Standalone HTML file with a print-optimized stylesheet. Save, print, or convert to PDF.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <svg class="h-4 w-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                            Email report
                        </h4>
                        <p class="mt-1.5 text-xs leading-5 text-slate-600">Send the HTML to any address. Rate-limited to prevent mistakes. Same content as the on-screen version.</p>
                    </div>
                </div>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 9. Keyword intelligence ============ --}}
            <section id="keyword-metrics" class="scroll-mt-24">
                {!! $sectionHeader('9', 'Keyword intelligence', 'The commercial layer. Every "Volume", "Value/mo", "CPC", and trend arrow reads from a privately-maintained keyword cache.') !!}

                <h3>What we expose</h3>
                <div class="not-prose my-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['Search volume', 'Monthly searches for the query.', '🔍'],
                        ['Commercial value', "A dollar estimate of what each keyword is worth to your business at a given position.", '💰'],
                        ['Competition', 'How hard this query is to win, shown as a 0–100 score. Lower = easier.', '⚔️'],
                        ['12-month trend', 'Rising, falling, seasonal, or stable — with the peak month where seasonality is detected.', '📈'],
                    ] as [$name, $desc, $icon])
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                            <div class="flex items-start gap-3">
                                <span class="text-2xl" aria-hidden="true">{{ $icon }}</span>
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900">{{ $name }}</h4>
                                    <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ $desc }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <h3>Trend classifications</h3>
                <p>Every keyword is tagged as one of five states. You'll see the icon next to the volume number on the rank-tracker, keywords table, and audit keyword-strategy section:</p>

                <div class="not-prose my-6 space-y-2.5">
                    @foreach ([
                        ['↑ rising', 'emerald', 'Search interest is growing over the last several months.'],
                        ['↓ falling', 'rose', "Search interest is shrinking. Watch for market decline, not just your rank."],
                        ['◐ seasonal', 'amber', 'Repeating peaks throughout the year — refresh content before the next peak hits.'],
                        ['— stable', 'slate', "We have trend data and it's flat."],
                        ['(blank)', 'slate', "Not enough trend history yet — will populate on the next refresh."],
                    ] as [$label, $tone, $meaning])
                        <div class="flex items-start gap-3 rounded-lg border border-{{ $tone }}-200 bg-{{ $tone }}-50/50 p-3">
                            <span class="rounded bg-{{ $tone }}-100 px-2 py-0.5 text-sm font-bold text-{{ $tone }}-700">{{ $label }}</span>
                            <p class="text-sm text-{{ $tone }}-900">{{ $meaning }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="not-prose my-6 rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 shrink-0 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.306a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941"/></svg>
                        <div>
                            <h4 class="text-sm font-semibold text-indigo-900">Our secret recipe</h4>
                            <p class="mt-1 text-sm leading-6 text-indigo-900/90">We combine search volume, commercial value, and your current rank into one projected-value number — then re-sort every opportunity list by dollar impact, not raw impressions. The exact weighting is how EBQ earns its keep; what you see on screen is the decision, not the math behind it.</p>
                        </div>
                    </div>
                </div>

                <h3>How data stays fresh without burning credits</h3>
                <ul>
                    <li><strong>Auto-fetch on nightly sync</strong> — high-impression queries get prioritized for enrichment.</li>
                    <li><strong>New tracked keyword</strong> — single fetch fires the moment you add it.</li>
                    <li><strong>Stale refresh</strong> — when any screen renders data older than our freshness window, it shows what we have and queues an update in the background.</li>
                </ul>
                <p class="text-sm text-slate-500">We never re-query data that's still in cache. A dash (—) means we haven't pulled that keyword yet — it fills in on the next background run.</p>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 10. Country filter ============ --}}
            <section id="country-filter" class="scroll-mt-24">
                {!! $sectionHeader('10', 'Country filter', 'A single dropdown scopes every downstream number to one country. URL-persisted so links are shareable.') !!}

                <div class="not-prose my-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-5 shadow-sm">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-emerald-900">
                            <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            Affected
                        </h4>
                        <ul class="mt-3 space-y-1.5 text-sm text-emerald-900/80">
                            <li>• Dashboard insight counts</li>
                            <li>• PPC-equivalent banner</li>
                            <li>• Cannibalizations, striking distance, index fails, content decay, quick wins tabs</li>
                            <li>• Growth report builder (Search Console section)</li>
                            <li>• Pages + Keywords tables</li>
                            <li>• Rank tracker's GSC column</li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-5 shadow-sm">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            Not affected
                        </h4>
                        <ul class="mt-3 space-y-1.5 text-sm text-slate-700">
                            <li>• Google Analytics (no country dimension in our schema today)</li>
                            <li>• Backlinks (third-party data)</li>
                            <li>• Audit vs traffic tab</li>
                            <li>• Audit reports themselves (per-URL, country-agnostic)</li>
                        </ul>
                    </div>
                </div>
                <p class="text-sm text-slate-500">Only countries where your site has recorded GSC impressions appear in the dropdown.</p>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 11. WordPress plugin ============ --}}
            <section id="wordpress-plugin" class="scroll-mt-24">
                {!! $sectionHeader('11', 'WordPress plugin guide', 'A customer-first walkthrough of EBQ SEO in WordPress: how to connect, what you will see, and how to use it to publish better pages.') !!}

                <div class="not-prose my-6 rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-indigo-700">Start here</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">Install EBQ SEO and connect in one flow</h3>
                            <p class="mt-1 text-sm text-slate-600">No API keys to copy. Click Connect, approve in EBQ, and return with data live.</p>
                        </div>
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
                            Sign in to download
                        </a>
                    </div>
                </div>

                <h3>Quick setup (2 minutes)</h3>
                <ol>
                    <li>Install the plugin from <strong>Plugins → Add New → Upload Plugin</strong>.</li>
                    <li>Activate <strong>EBQ SEO</strong>.</li>
                    <li>Go to <strong>Settings → EBQ SEO</strong> and click <strong>Connect to EBQ</strong>.</li>
                    <li>Pick your website in EBQ and approve access.</li>
                    <li>Return to WordPress and start editing any post to see live SEO guidance.</li>
                </ol>

                <h3>Plugin visuals you will see</h3>
                <div class="not-prose my-6 grid gap-4 lg:grid-cols-3">
                    <figure class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <figcaption class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Screen 1 · Settings → EBQ SEO</figcaption>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-900">Connect this site to EBQ</span>
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Step 1</span>
                            </div>
                            <p class="text-[11px] text-slate-600">One click. No token copy/paste.</p>
                            <button type="button" class="mt-3 w-full rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Connect to EBQ</button>
                        </div>
                        <p class="mt-3 text-xs text-slate-600">This is where connection starts and where you can refresh data later.</p>
                    </figure>

                    <figure class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <figcaption class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Screen 2 · Editor sidebar</figcaption>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-900">EBQ SEO</span>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Score 72</span>
                            </div>
                            <div class="mt-2 grid grid-cols-3 gap-1 text-[10px]">
                                <span class="rounded bg-white px-2 py-1 text-center font-medium text-slate-700">SEO</span>
                                <span class="rounded bg-white px-2 py-1 text-center font-medium text-slate-700">Readability</span>
                                <span class="rounded bg-white px-2 py-1 text-center font-medium text-slate-700">Insights</span>
                            </div>
                            <ul class="mt-3 space-y-1 text-[11px] text-slate-600">
                                <li>• Focus keyphrase check</li>
                                <li>• Title/meta optimization</li>
                                <li>• Live content recommendations</li>
                            </ul>
                        </div>
                        <p class="mt-3 text-xs text-slate-600">Writers use this while editing to improve content before publish.</p>
                    </figure>

                    <figure class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <figcaption class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Screen 3 · WordPress dashboard widget</figcaption>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="mb-2 text-xs font-semibold text-slate-900">EBQ SEO insights</div>
                            <div class="grid grid-cols-2 gap-2 text-[10px]">
                                <div class="rounded bg-white p-2"><p class="text-slate-500">Cannibalizations</p><p class="text-sm font-bold text-rose-600">4</p></div>
                                <div class="rounded bg-white p-2"><p class="text-slate-500">Striking distance</p><p class="text-sm font-bold text-amber-600">12</p></div>
                                <div class="rounded bg-white p-2"><p class="text-slate-500">Index fails + traffic</p><p class="text-sm font-bold text-rose-600">2</p></div>
                                <div class="rounded bg-white p-2"><p class="text-slate-500">Content decay</p><p class="text-sm font-bold text-amber-600">7</p></div>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-slate-600">A daily snapshot for what to fix first, with links into full EBQ reports.</p>
                    </figure>
                </div>

                <h3>What customers can do with the plugin</h3>
                <div class="not-prose my-6 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['Improve content before publishing', 'Use SEO and Readability checks directly in the editor to reduce guesswork.'],
                        ['Find quick wins faster', 'See striking-distance and cannibalization signals without leaving WordPress.'],
                        ['Monitor site health from dashboard', 'Track content decay and indexing-risk pages in one widget.'],
                        ['Control SEO essentials', 'Manage title, meta description, schema, social, canonical, and robots settings per page.'],
                    ] as [$title, $desc])
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <h4 class="text-sm font-semibold text-slate-900">{{ $title }}</h4>
                            <p class="mt-1.5 text-xs leading-5 text-slate-600">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- ============ NEW: Live SEO score ============ --}}
                <h3 id="wp-live-score" class="scroll-mt-24">Live SEO score (the centerpiece)</h3>
                <p>The score next to every post is composed server-side from real data — Google Search Console performance, indexing status, backlinks, Core Web Vitals, and an EBQ-side page audit. It updates automatically every time you save the post. Free and Pro both get this.</p>
                <div class="not-prose my-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-4">
                            <div class="flex items-center gap-3">
                                <div class="relative grid h-14 w-14 place-items-center rounded-full" style="background: conic-gradient(#16a34a 78%, #e5e7eb 0);">
                                    <div class="absolute inset-1 rounded-full bg-white"></div>
                                    <span class="relative text-sm font-extrabold text-slate-900">78</span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500"><span class="rounded bg-emerald-600 px-1.5 py-0.5 text-white">EBQ</span> · Live SEO score</p>
                                    <p class="mt-1 text-sm font-semibold text-emerald-700">Good</p>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-emerald-900">Composed from 13 real signals, refreshed automatically.</p>
                        </div>
                        <div class="rounded-xl border border-blue-200 bg-blue-50/40 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500"><span class="rounded bg-blue-600 px-1.5 py-0.5 text-white">Self-check</span> · On-page (offline)</p>
                            <p class="mt-2 text-sm font-semibold text-blue-900">Local heuristics: title length, keyword in H1, meta length, etc.</p>
                            <p class="mt-2 text-xs text-blue-900/80">Always works, even before EBQ is connected.</p>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-2 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            ['Focus-keyword rank', '22%', 'Position 7 for "vegan protein"'],
                            ['Click-through rate', '6%', 'CTR 4.1% (expected 3.6%)'],
                            ['Topical coverage', '6%', '14 distinct queries in top 100'],
                            ['No cannibalization', '4%', 'No competing pages'],
                            ['Google index status', '12%', 'Verdict: PASS · Submitted and indexed'],
                            ['Backlinks', '4%', '23 referring domains'],
                            ['Core Web Vitals', '10%', 'LCP 2,100ms · CLS 0.05'],
                            ['Page performance', '6%', 'Mobile 87 / Desktop 93'],
                            ['On-page SEO', '6%', 'Title 54 · Meta 142 · H1: 1'],
                            ['Technical health', '6%', 'HTTP 200 · HTTPS · 312ms TTFB'],
                            ['Content quality', '5%', '1,840 words · Flesch 64'],
                            ['Keyword placement', '7%', 'In title, H1, meta, body'],
                            ['Top fixes (RecommendationEngine)', '8%', 'Critical/warning items as actionable cards'],
                        ] as [$factor, $weight, $detail])
                            <div class="rounded-lg border border-slate-200 bg-white p-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold text-slate-900">{{ $factor }}</span>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-600">{{ $weight }}</span>
                                </div>
                                <p class="mt-1 text-[10px] text-slate-500">{{ $detail }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                <p class="text-sm text-slate-600"><strong>Bonus intelligence:</strong> if Google features dominate the SERP for your keyword (answer box, People Also Ask, image pack pushed organic below the fold), the rank score is automatically discounted with an explanation. You see "your rank reads worse than the raw position" instead of a misleadingly high number.</p>

                {{-- ============ NEW: AI Snippet Rewrites ============ --}}
                <h3 id="wp-ai-rewrites" class="scroll-mt-24">AI title + meta rewrites <span class="ml-2 rounded bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-700">PRO</span></h3>
                <p>One click → 3 ranked rewrites with rationale. The model sees your current copy, the page body for intent grounding, and the top-3 competitor titles for differentiation. Apply title, meta, or both — straight into the post.</p>
                <div class="not-prose my-6 rounded-2xl border border-purple-200 bg-gradient-to-br from-purple-50 to-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h4 class="text-sm font-bold text-purple-900">✨ AI snippet rewrites</h4>
                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-700">cached 7 days</span>
                    </div>
                    <div class="space-y-3">
                        @foreach ([
                            ['commercial', 'Best Vegan Protein Powder 2026: Top 12 Ranked', 'Backed by lab-tested protein content per scoop, plus our top picks for muscle gain and recovery.', 'Leads with year + ranking — captures comparison-shopper intent the competitor titles miss.'],
                            ['guide', 'Vegan Protein Powder Guide: How to Pick (and Use) the Right One', 'Plant-based protein explained — pea, rice, soy, hemp — with daily-dose recommendations and lab-tested top picks.', 'Educational angle — wider funnel reach for "how to" queries the SERP currently underserves.'],
                            ['comparison', 'Pea vs Rice vs Soy: Which Vegan Protein Wins in 2026?', 'Side-by-side: protein content, amino profile, digestibility, taste. Lab-tested. Plus our top 3 blends.', 'Comparison angle — strong CTR pull when SERP is dominated by single-product reviews.'],
                        ] as [$angle, $title, $meta, $rationale])
                            <div class="rounded-lg border border-purple-200 bg-white p-3 shadow-sm">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-purple-700">{{ $angle }}</span>
                                    <span class="text-[10px] text-slate-400">Title {{ strlen($title) }} · Meta {{ strlen($meta) }}</span>
                                </div>
                                <p class="text-sm font-bold text-slate-900">{{ $title }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ $meta }}</p>
                                <p class="mt-2 text-[11px] italic text-slate-500">{{ $rationale }}</p>
                                <div class="mt-2 flex gap-2">
                                    <button class="rounded border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-slate-700">Use title</button>
                                    <button class="rounded border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-slate-700">Use meta</button>
                                    <button class="rounded bg-purple-600 px-2 py-1 text-[10px] font-semibold text-white">Use both</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ============ NEW: Content Brief Tab ============ --}}
                <h3 id="wp-content-brief" class="scroll-mt-24">AI content brief <span class="ml-2 rounded bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-700">PRO</span></h3>
                <p>New tab in the editor sidebar. Type a target keyword → EBQ scrapes the top 10 SERP, runs the LLM, and returns a writer-ready brief: subtopics to cover, recommended depth, schema type, H2 outline, must-have entities, "people also ask", and internal-link targets pulled from your own GSC data.</p>
                <div class="not-prose my-6 rounded-2xl border border-purple-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 grid grid-cols-3 gap-3">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
                            <div class="text-2xl font-extrabold text-slate-900">1,800</div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-500">words target</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
                            <div class="text-2xl font-extrabold text-slate-900">Article</div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-500">schema</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
                            <div class="text-2xl font-extrabold text-slate-900">12</div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-500">subtopics</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h4 class="text-xs font-bold text-slate-900">Suggested H2 outline</h4>
                        <ol class="mt-1 list-decimal pl-5 text-xs text-slate-700">
                            <li>What makes a vegan protein "complete"</li>
                            <li>The 4 main plant protein sources compared</li>
                            <li>How to pick the right one for your goal</li>
                            <li>Lab-tested top picks for 2026</li>
                            <li>Daily dosage and timing</li>
                            <li>Common allergens and mixing tips</li>
                        </ol>
                    </div>
                    <div class="mb-3">
                        <h4 class="text-xs font-bold text-slate-900">Subtopics to cover</h4>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @foreach (['amino acid profile', 'pea protein', 'rice protein', 'soy protein', 'hemp protein', 'BCAA content', 'leucine threshold', 'digestibility', 'protein blends', 'allergens', 'taste', 'mixability'] as $t)
                                <span class="rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-[10px] text-purple-900">{{ $t }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-900">Internal link targets (from your GSC)</h4>
                        <ul class="mt-1 space-y-1 text-[11px]">
                            <li class="rounded border border-slate-200 bg-slate-50 p-2"><a class="font-semibold text-blue-700">/blog/best-protein-bars-2026</a><span class="ml-2 text-slate-500">anchor: "best protein bars" · 142 clicks/30d</span></li>
                            <li class="rounded border border-slate-200 bg-slate-50 p-2"><a class="font-semibold text-blue-700">/recipes/vegan-protein-shake</a><span class="ml-2 text-slate-500">anchor: "vegan protein shake" · 89 clicks/30d</span></li>
                        </ul>
                    </div>
                </div>

                {{-- ============ NEW: Entity Coverage ============ --}}
                <h3 id="wp-entity-coverage" class="scroll-mt-24">Entity coverage (E-E-A-T)</h3>
                <p>Inside the editor's SEO tab. Click "Analyze entity coverage" → EBQ extracts the people, brands, products, and concepts your page mentions, compares against the top-3 competitors, and lists what they cover that you don't — with one-line "why this matters" rationales.</p>
                <div class="not-prose my-6 rounded-2xl border border-amber-200 bg-amber-50/40 p-5 shadow-sm">
                    <div class="mb-4 grid grid-cols-3 gap-3">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-center">
                            <div class="text-2xl font-extrabold text-emerald-700">14</div>
                            <div class="text-[10px] uppercase tracking-wider text-emerald-700">you cover</div>
                        </div>
                        <div class="rounded-lg border border-amber-300 bg-amber-100 p-3 text-center">
                            <div class="text-2xl font-extrabold text-amber-700">5</div>
                            <div class="text-[10px] uppercase tracking-wider text-amber-700">missing vs top 3</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-3 text-center">
                            <div class="text-2xl font-extrabold text-slate-700">19</div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-500">competitor entities</div>
                        </div>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">Entities to add</h4>
                    <ul class="mt-2 space-y-2">
                        @foreach ([
                            ['NSF Certified for Sport', 'concept', 'A trust signal competitors mention to credential their lab-test claims — adds authority signals Google associates with E-E-A-T.'],
                            ['Examine.com', 'org', 'Top competitor cites this independent supplement research site as their evidence base. Citing recognized authorities boosts topical authority.'],
                            ['leucine threshold', 'concept', 'Competitors discuss the 2.5g leucine threshold for muscle protein synthesis. Missing means your content reads as less expert.'],
                        ] as [$entity, $type, $why])
                            <li class="rounded-lg border border-amber-200 bg-white p-3">
                                <div class="flex items-center gap-2">
                                    <strong class="text-sm text-slate-900">{{ $entity }}</strong>
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-800">{{ $type }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-600">{{ $why }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- ============ NEW: Topical Gaps ============ --}}
                <h3 id="wp-topical-gaps" class="scroll-mt-24">Topical gaps vs the top SERP</h3>
                <p>Manual trigger inside the SEO tab. EBQ scrapes the top-5 ranking pages for your focus keyword, asks the model to extract subtopics from each side, and returns the missing ones with competitor source links. The "where do I expand my draft?" answer in 15 seconds.</p>
                <div class="not-prose my-6 rounded-2xl border border-rose-200 bg-rose-50/40 p-5 shadow-sm">
                    <div class="mb-3 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded bg-white p-2"><div class="text-xl font-extrabold text-emerald-700">8</div><div class="text-[10px] text-slate-500">subtopics you cover</div></div>
                        <div class="rounded bg-white p-2"><div class="text-xl font-extrabold text-rose-600">4</div><div class="text-[10px] text-slate-500">missing vs top 5</div></div>
                        <div class="rounded bg-white p-2"><div class="text-xl font-extrabold text-slate-700">12</div><div class="text-[10px] text-slate-500">competitor subtopics</div></div>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">Subtopics to add</h4>
                    <ul class="mt-2 space-y-2 text-xs">
                        @foreach ([
                            ['Allergen labeling standards', 'Top 3 ranking competitors devote a section to FDA / EU allergen labels. Adding this answers a buyer-anxiety question that drives bounces.'],
                            ['Heavy metal lab tests', 'Recurring concern in "people also ask" — competitors all address it; your draft doesn\'t.'],
                            ['Cost per serving comparison', 'Direct purchase decision factor. Two competitors include calculators / tables.'],
                        ] as [$topic, $why])
                            <li class="rounded border border-rose-200 bg-white p-2">
                                <strong class="text-slate-900">{{ $topic }}</strong>
                                <p class="mt-1 text-[11px] text-slate-600">{{ $why }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- ============ NEW: AI Redirect Matcher ============ --}}
                <h3 id="wp-redirects-ai" class="scroll-mt-24">AI redirect suggestions from 404 logs</h3>
                <p>The plugin captures front-end 404s on every page load (filtered for bots, admin, REST). An hourly background job ships them to EBQ where the LLM matches each broken URL to the best replacement page on your site — using your GSC inventory as the candidate list. You review in HQ → Redirects (AI) and apply with one click. The 301 serves immediately from the local rule store.</p>
                <div class="not-prose my-6 rounded-2xl border border-blue-200 bg-white p-5 shadow-sm">
                    <div class="space-y-3">
                        @foreach ([
                            ['/blog/old-vegan-guide', '/blog/vegan-protein-powder-guide', 88, 412, 'Same intent — both target the head query "vegan protein guide". Old slug from a 2023 rewrite.'],
                            ['/products/protein/discontinued', '/products/protein/category', 76, 87, 'Discontinued SKU — closest replacement is the parent category page.'],
                            ['/recipes/old-shake', '/recipes/vegan-protein-shake', 92, 28, 'Slug change tracked in GSC. Direct 1:1 match.'],
                        ] as [$from, $to, $conf, $hits, $why])
                            @php $tone = $conf >= 80 ? ['ring' => 'green', 'badge' => 'bg-emerald-100 text-emerald-700'] : ($conf >= 50 ? ['ring' => 'amber', 'badge' => 'bg-amber-100 text-amber-700'] : ['ring' => 'red', 'badge' => 'bg-rose-100 text-rose-700']); @endphp
                            <div class="grid grid-cols-[80px_1fr_auto] items-center gap-4 rounded-lg border border-slate-200 bg-white p-3 shadow-sm" style="border-left: 3px solid {{ $tone['ring'] === 'green' ? '#16a34a' : ($tone['ring'] === 'amber' ? '#d97706' : '#dc2626') }};">
                                <div class="text-center">
                                    <div class="relative mx-auto grid h-12 w-12 place-items-center rounded-full" style="background: conic-gradient({{ $tone['ring'] === 'green' ? '#16a34a' : ($tone['ring'] === 'amber' ? '#d97706' : '#dc2626') }} {{ $conf }}%, #f1f5f9 0);">
                                        <div class="absolute inset-1 rounded-full bg-white"></div>
                                        <span class="relative text-sm font-extrabold text-slate-900">{{ $conf }}</span>
                                    </div>
                                    <div class="mt-1 text-[9px] uppercase tracking-wider text-slate-400">confidence</div>
                                </div>
                                <div class="min-w-0">
                                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                                        <div>
                                            <div class="text-[9px] uppercase tracking-wider text-slate-400">From</div>
                                            <code class="block truncate rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-900">{{ $from }}</code>
                                        </div>
                                        <span class="text-lg font-bold text-slate-400">→</span>
                                        <div>
                                            <div class="text-[9px] uppercase tracking-wider text-slate-400">To (editable)</div>
                                            <input type="text" value="{{ $to }}" readonly class="block w-full truncate rounded border border-slate-300 bg-white px-2 py-1 font-mono text-[11px] text-slate-900">
                                        </div>
                                    </div>
                                    <p class="mt-2 text-[11px] italic text-slate-500">{{ $why }}</p>
                                    <div class="mt-1 flex gap-3 text-[10px] text-slate-500">
                                        <span><strong class="text-slate-900">{{ $hits }}</strong> hits/30d</span>
                                        <span class="rounded {{ $tone['badge'] }} px-2 py-0.5 font-bold">pending</span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <button class="rounded bg-blue-600 px-3 py-1 text-[10px] font-bold text-white">Apply</button>
                                    <button class="rounded border border-slate-200 bg-white px-3 py-1 text-[10px] font-bold text-slate-700">Reject</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-4 text-xs text-slate-600">High-confidence (≥80%) suggestions can be applied in bulk with one click.</p>
                </div>

                {{-- ============ NEW: Backlink Prospects ============ --}}
                <h3 id="wp-prospects" class="scroll-mt-24">Backlink prospecting (auto-discovered)</h3>
                <p>EBQ pulls competitor domains from your recent page audits, finds referring domains that link to your competitors but NOT to you, and ranks them by domain authority. A nightly background job keeps the list fresh — you open the tab in the morning and see new prospects waiting. <strong>No manual competitor entry required.</strong></p>
                <div class="not-prose my-6 rounded-2xl border border-blue-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900">
                        <span class="font-semibold">✓</span> Auto-discovered <strong>12 competitors</strong> from your audits → <strong>47 total prospects</strong> (8 new this run).
                    </div>
                    <div class="mb-3 flex gap-2 text-[10px]">
                        @foreach (['new · 8' => true, 'drafted · 3', 'contacted · 2', 'replied · 1', 'converted', 'declined', 'snoozed'] as $key => $val)
                            @php $label = is_string($key) ? $key : $val; $active = $val === true; @endphp
                            <span class="rounded-full {{ $active ? 'bg-blue-600 text-white' : 'border border-slate-200 text-slate-600' }} px-2 py-1 font-semibold">{{ $label }}</span>
                        @endforeach
                    </div>
                    <table class="w-full text-xs">
                        <thead class="border-b border-slate-200 text-left text-[10px] uppercase tracking-wider text-slate-500">
                            <tr><th class="pb-2">Domain</th><th class="pb-2">DA</th><th class="pb-2">Linked to</th><th class="pb-2">Status</th><th class="pb-2">Action</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ([
                                ['plantbasedreviews.com', 78, 'competitor1.com, competitor2.com'],
                                ['veganmuscle.org', 64, 'competitor1.com'],
                                ['fitnessjournal.io', 52, 'competitor2.com, competitor3.com'],
                            ] as [$d, $da, $links])
                                <tr>
                                    <td class="py-2"><a class="font-semibold text-blue-700">{{ $d }}</a></td>
                                    <td class="py-2"><strong>{{ $da }}</strong></td>
                                    <td class="py-2 text-slate-500">{{ $links }}</td>
                                    <td class="py-2"><select class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px]"><option>new</option></select></td>
                                    <td class="py-2"><button class="rounded bg-purple-600 px-2 py-0.5 text-[10px] font-bold text-white">✨ Draft outreach</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-3 text-xs text-slate-600">Click "Draft outreach" → AI writes a personalized 90-word email referencing why they linked to the competitor. <span class="rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-bold text-purple-700">PRO</span> Status, notes, and drafts persist across sessions — real outreach kanban, not a re-compute tool.</p>
                </div>

                {{-- ============ NEW: SERP Features ============ --}}
                <h3 id="wp-serp-features" class="scroll-mt-24">Live SERP features tracking</h3>
                <p>For every keyword in your Rank Tracker, see which Google features appeared today and whether you OWN any of them (your domain inside the answer box, sitelinks, image pack). 30-day timeline shows feature volatility — anything that comes and goes is an outreach opportunity.</p>
                <div class="not-prose my-6 rounded-2xl border border-indigo-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 grid grid-cols-4 gap-3">
                        @foreach ([
                            ['68%', 'with answer box', 'good'],
                            ['54%', 'with PAA', 'good'],
                            ['32%', 'with image pack', 'warn'],
                            ['89%', 'any feature', 'good'],
                        ] as [$pct, $label, $tone])
                            <div class="rounded-lg border {{ $tone === 'good' ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-3 text-center">
                                <div class="text-2xl font-extrabold text-slate-900">{{ $pct }}</div>
                                <div class="text-[10px] uppercase tracking-wider text-slate-500">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                    <table class="w-full text-xs">
                        <thead class="border-b border-slate-200 text-left text-[10px] uppercase tracking-wider text-slate-500">
                            <tr><th class="pb-2">Keyword</th><th class="pb-2">Features today</th><th class="pb-2">You own</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ([
                                ['vegan protein powder', ['Answer Box', 'PAA', 'Images'], ['Answer Box']],
                                ['plant protein vs whey', ['PAA'], []],
                                ['best protein bars', ['Images', 'Sitelinks'], ['Images']],
                            ] as [$kw, $features, $owned])
                                <tr>
                                    <td class="py-2 font-semibold">{{ $kw }}</td>
                                    <td class="py-2">@foreach ($features as $f)<span class="mr-1 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-800">{{ $f }}</span>@endforeach</td>
                                    <td class="py-2">@if (count($owned))@foreach ($owned as $f)<span class="mr-1 rounded-full bg-emerald-700 px-2 py-0.5 text-[10px] font-bold text-white">✓ {{ $f }}</span>@endforeach @else <span class="text-slate-400">—</span> @endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- ============ NEW: Network Benchmarks ============ --}}
                <h3 id="wp-benchmarks" class="scroll-mt-24">Network benchmarks (anonymous)</h3>
                <p>Compare your site against the entire EBQ network (anonymized aggregate stats, minimum cohort size 5 for privacy). The percentile is the single most useful number on this screen — agencies use it to prove progress to clients without exposing competitor data.</p>
                <div class="not-prose my-6 rounded-2xl border border-blue-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center gap-4 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 p-5">
                        <div class="text-5xl font-extrabold text-blue-700">73<sup class="text-xl">th</sup></div>
                        <div>
                            <div class="text-sm font-semibold text-slate-900">percentile</div>
                            <p class="mt-1 text-xs text-slate-600">Your site ranks better than <strong>73%</strong> of the 142 sites in the network cohort.</p>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ([
                            ['You', 'border-blue-300 bg-blue-50', [['Avg position', '8.4'], ['Median (p50)', '—'], ['Avg CTR', '4.1%'], ['Queries/30d', '847']]],
                            ['Network avg', 'border-slate-200', [['Avg position', '14.2'], ['Median (p50)', '12.8'], ['Top 10% (p90)', '5.1'], ['Avg CTR', '2.8%'], ['n=142 sites', '']]],
                            ['US cohort', 'border-slate-200', [['Avg position', '13.6'], ['Avg CTR', '3.0%'], ['n=87 sites', '']]],
                        ] as [$label, $cls, $rows])
                            <div class="rounded-lg border {{ $cls }} bg-white p-3">
                                <div class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                                @foreach ($rows as [$k, $v])
                                    <div class="flex justify-between py-0.5 text-[11px]"><span class="text-slate-500">{{ $k }}</span><strong class="text-slate-900">{{ $v }}</strong></div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ============ NEW: Topical Authority ============ --}}
                <h3 id="wp-topical-authority" class="scroll-mt-24">Topical authority map</h3>
                <p>EBQ clusters your GSC queries into topical groups, scores each by depth × traffic × position, and surfaces gaps. Low-authority high-impression clusters are explicit content opportunities — write a definitive page for them.</p>
                <div class="not-prose my-6 rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 rounded-lg border-l-4 border-amber-400 bg-amber-50 p-3">
                        <h4 class="text-xs font-bold text-amber-900">⚡ Content opportunities</h4>
                        <ul class="mt-2 space-y-2 text-xs">
                            <li><strong class="text-slate-900">protein bar reviews</strong><p class="text-slate-600">Cluster averages position 31.2 on 4,200 impressions/90d — write a definitive page targeting "best protein bars" plus 2–3 related queries.</p></li>
                        </ul>
                    </div>
                    <table class="w-full text-xs">
                        <thead class="border-b border-slate-200 text-left text-[10px] uppercase tracking-wider text-slate-500">
                            <tr><th class="pb-2">Cluster</th><th class="pb-2">Authority</th><th class="pb-2">Avg pos</th><th class="pb-2">Clicks/90d</th><th class="pb-2">Pages</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ([
                                ['vegan protein powder', 84, '3.2', 4180, 6, 'good'],
                                ['plant protein recipes', 71, '6.8', 2104, 8, 'good'],
                                ['protein bars 2026', 32, '31.2', 87, 1, 'bad'],
                            ] as [$label, $score, $pos, $clicks, $pages, $tone])
                                <tr>
                                    <td class="py-2 font-semibold">{{ $label }}</td>
                                    <td class="py-2"><span class="rounded {{ $tone === 'good' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }} px-2 py-0.5 font-bold">{{ $score }}</span></td>
                                    <td class="py-2 font-mono">{{ $pos }}</td>
                                    <td class="py-2 font-mono">{{ $clicks }}</td>
                                    <td class="py-2 font-mono">{{ $pages }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- ============ NEW: Behind the scenes ============ --}}
                <h3 id="wp-automatic" class="scroll-mt-24">What happens automatically (no clicks needed)</h3>
                <div class="not-prose my-6 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['🔁', 'Re-audit on post update', 'Edit a post → on next save the live score detects you changed it (compares post_modified to last audit timestamp) and queues a fresh audit. The breakdown auto-refreshes via polling — no manual reload.'],
                        ['🔗', 'Backlinks sync from Keywords Everywhere', 'Once per month per domain, EBQ pulls fresh backlinks via the Keywords Everywhere API. Universal freshness gate prevents duplicate billing across competitor / own / page-audit code paths.'],
                        ['🧭', '404 capture + redirect matching', 'Plugin tracks front-end 404s (filtered for bots), ships them hourly to EBQ. AI matches each broken URL to the best replacement on your site. You review + apply in HQ.'],
                        ['🎯', 'Auto-discover backlink prospects', 'Nightly background job pulls competitor domains from your recent audits, runs prospect-discovery against each, and adds new prospects to your kanban. You wake up to a refreshed outreach list.'],
                        ['📊', 'Lite-mode editor audits', 'When the live score auto-queues an audit (because you opened a post that was never audited), it runs in lite mode — skips link-checking + competitor SERP fetches. Completes in 15–30s instead of 60–120s.'],
                        ['⚡', 'Reactive Pro tier sync', 'Upgrade or downgrade in EBQ → next API response in the editor flips the UI within one save. No reconnect required.'],
                    ] as [$icon, $title, $desc])
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="mb-1 flex items-center gap-2">
                                <span class="text-lg">{{ $icon }}</span>
                                <h4 class="text-sm font-semibold text-slate-900">{{ $title }}</h4>
                            </div>
                            <p class="text-xs leading-5 text-slate-600">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- ============ NEW: Pro vs Free ============ --}}
                <h3 id="wp-pro-vs-free" class="scroll-mt-24">Free vs Pro</h3>
                <div class="not-prose my-6 overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Feature</th>
                                <th class="px-4 py-2.5 text-center font-semibold">Free</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-purple-700">Pro</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Live SEO score (13 factors + audit)', '✓', '✓'],
                                ['Topical gaps vs top 5 SERP', '✓', '✓'],
                                ['Entity coverage analysis', '✓', '✓'],
                                ['Editor sidebar + post-list scores', '✓', '✓'],
                                ['HQ — overview, pages, keywords, insights', '✓', '✓'],
                                ['Rank Tracker', '✓', '✓'],
                                ['Live SERP features tracking', '✓', '✓'],
                                ['Network benchmarks', '✓', '✓'],
                                ['Topical authority map', '✓', '✓'],
                                ['AI redirect suggestions from 404s', '✓', '✓'],
                                ['Backlink prospect discovery + persisted kanban', '✓', '✓'],
                                ['AI title + meta rewrites', '—', '✓'],
                                ['AI content brief tab', '—', '✓'],
                                ['AI outreach email drafting', '—', '✓'],
                            ] as [$feature, $free, $pro])
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2.5">{{ $feature }}</td>
                                    <td class="px-4 py-2.5 text-center {{ $free === '—' ? 'text-slate-300' : 'text-emerald-600' }} font-bold">{{ $free }}</td>
                                    <td class="px-4 py-2.5 text-center {{ $pro === '—' ? 'text-slate-300' : 'text-emerald-600' }} font-bold">{{ $pro }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3>Before you hit publish</h3>
                <div class="not-prose my-6 rounded-xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
                    <ul class="space-y-2 text-sm text-emerald-900">
                        <li class="flex items-start gap-2"><span class="mt-0.5">✓</span><span>Live SEO score is healthy for your target keyword.</span></li>
                        <li class="flex items-start gap-2"><span class="mt-0.5">✓</span><span>Title and description look good in preview and are not too long.</span></li>
                        <li class="flex items-start gap-2"><span class="mt-0.5">✓</span><span>"Top fixes" factor has no critical items remaining.</span></li>
                        <li class="flex items-start gap-2"><span class="mt-0.5">✓</span><span>Keyword placement factor confirms focus keyphrase is in title, H1, meta, and body.</span></li>
                        <li class="flex items-start gap-2"><span class="mt-0.5">✓</span><span>Entity-coverage analysis surfaces no must-add entities.</span></li>
                        <li class="flex items-start gap-2"><span class="mt-0.5">✓</span><span>Topical gaps panel returns "no gaps found" or you've added the missing subtopics.</span></li>
                        <li class="flex items-start gap-2"><span class="mt-0.5">✓</span><span>Canonical and robots settings match the page purpose.</span></li>
                    </ul>
                </div>

                <h3>Common issues and fixes</h3>
                <div class="not-prose my-6 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['I cannot connect the site', 'Go to Settings → EBQ SEO and retry Connect to EBQ while logged into the correct EBQ account.'],
                        ['I do not see insights in the editor', 'Open a post with enough search history and confirm the plugin still shows as connected.'],
                        ['Dashboard numbers look old', 'Use Refresh data in plugin settings, then reload wp-admin.'],
                        ['A page has SEO warnings', 'Open the sidebar SEO/Readability tabs and apply the top 2-3 suggestions first.'],
                    ] as [$issue, $fix])
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <h4 class="text-sm font-semibold text-slate-900">{{ $issue }}</h4>
                            <p class="mt-1.5 text-xs leading-5 text-slate-600">{{ $fix }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <hr class="my-14 border-slate-200">

            {{-- ============ 12. Glossary ============ --}}
            <section id="glossary" class="scroll-mt-24">
                {!! $sectionHeader('12', 'Glossary', 'Quick definitions for the terms that appear across the app.') !!}

                <div class="not-prose my-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-2.5 font-semibold">Term</th><th class="px-4 py-2.5 font-semibold">Definition</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['At stake', "The dollar value of a keyword if a single page owned it — what a cannibalization split is costing you."],
                                ['Cannibalization', 'Two or more of your pages competing for the same query and splitting click share.'],
                                ['Content decay', 'Sustained click drop on a page with enough impressions to still be recoverable.'],
                                ['Coverage score', 'Share of your top GSC queries that actually appear in the page body.'],
                                ['CPC', 'Average cost advertisers pay to show up for a query. A proxy for commercial intent.'],
                                ['CTR', 'Click-through rate — clicks divided by impressions.'],
                                ['Custom audit', 'User-triggered audit of a specific URL and target keyword.'],
                                ['Decay reason', "<code>recoverable</code> (page lost rank) or <code>market_decline</code> (query itself is fading)."],
                                ['Impressions', 'Times your page appeared in a search result a user saw.'],
                                ['Index verdict', "Google's word on whether your page is indexable — PASS, FAIL, or specific reason."],
                                ['LCP', 'Largest Contentful Paint — Core Web Vital measuring when the main visual element loads.'],
                                ['PPC equivalent', "Ad spend you'd need to replicate your organic traffic at current CPC rates."],
                                ['Primary keyword', 'The query we consider most representative of a page.'],
                                ['Projected value', 'Estimated monthly dollar value at the current rank.'],
                                ['Quick win', "Keyword with real volume, low competition, where you don't yet rank top-10."],
                                ['Recommendation severity', 'critical / warning / SERP gap / info / good — audit-report pill colors.'],
                                ['SERP feature', 'Anything non-organic on a Google results page — AI overview, snippet, PAA, etc.'],
                                ['Striking distance', 'Query ranking just outside page 1 with real impressions — one push to win.'],
                                ['Target keyword', 'Keyword you specify when queuing a custom audit.'],
                                ['Trend class', 'rising / falling / seasonal / stable / unknown — 12-month volume pattern.'],
                                ['Upside value', 'Extra monthly dollars you\'d earn if a keyword climbed into the top of page 1.'],
                                ['Volume', 'Monthly search volume.'],
                                ['YoY', 'Year-over-year comparison.'],
                            ] as [$term, $def])
                                <tr class="transition hover:bg-slate-50">
                                    <td class="w-48 px-4 py-3 align-top font-semibold text-slate-900">{{ $term }}</td>
                                    <td class="px-4 py-3 align-top">{!! $def !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- ============ CTA ============ --}}
            <section class="not-prose mt-20 overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-slate-900 p-8 text-center text-white shadow-xl sm:p-12">
                <h2 class="text-2xl font-bold sm:text-3xl">Operate on real signal, not dashboards.</h2>
                <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-indigo-100 sm:text-base">
                    Every surface above comes alive the moment you connect Search Console. Free to start — no credit card required.
                </p>
                <div class="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-indigo-700 transition hover:bg-slate-100">Start free trial</a>
                    <a href="{{ route('features') }}" class="inline-flex items-center justify-center rounded-md border border-white/30 bg-white/10 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/20">Marketing overview</a>
                </div>
            </section>
        </article>
    </main>

    {{-- ============ Footer ============ --}}
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

    {{-- Scroll-to-top button --}}
    <button id="scroll-top" type="button" aria-label="Scroll to top"
            class="scroll-top fixed bottom-6 right-6 z-30 flex h-11 w-11 items-center justify-center rounded-full bg-slate-900 text-white shadow-lg transition hover:bg-indigo-600">
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/></svg>
    </button>

    <script>
        // Sticky TOC active-link highlighting.
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

        // Scroll-to-top button visibility + click.
        (function () {
            const btn = document.getElementById('scroll-top');
            if (! btn) return;
            const threshold = 600;
            const onScroll = () => {
                if (window.scrollY > threshold) btn.classList.add('visible');
                else btn.classList.remove('visible');
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
            onScroll();
        })();
    </script>
</body>
</html>
