<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">

    <title>EBQ — SEO intelligence that drives growth</title>
    <meta name="description" content="EBQ unifies rankings, analytics, backlinks, and page performance in one workspace so growth teams can ship the work that moves pipeline.">
    <link rel="canonical" href="{{ url('/') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="EBQ — SEO intelligence that drives growth">
    <meta property="og:description" content="One elegant command center for SEO teams. Track rankings, backlinks, and page performance, then report with confidence.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:site_name" content="EBQ">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="EBQ — SEO intelligence that drives growth">
    <meta name="twitter:description" content="One elegant command center for SEO teams. Track rankings, backlinks, and page performance, then report with confidence.">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @if (! app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-full bg-slate-950 font-sans text-white antialiased selection:bg-indigo-500/40 selection:text-white">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-950">Skip to content</a>

    <div class="relative isolate overflow-x-clip">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[44rem] bg-[radial-gradient(circle_at_15%_-10%,rgba(99,102,241,0.45),transparent_45%),radial-gradient(circle_at_85%_10%,rgba(14,165,233,0.28),transparent_42%),linear-gradient(180deg,#020617_0%,#0b1220_55%,#f8fafc_100%)]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[44rem] bg-[radial-gradient(circle_at_50%_0,rgba(255,255,255,0.06),transparent_60%)]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-[8rem] -z-10 mx-auto h-px max-w-5xl bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>

        <header class="sticky top-0 z-30 border-b border-white/5 bg-slate-950/60 backdrop-blur-md">
            <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
                <a href="/" class="flex items-center gap-3" aria-label="EBQ home">
                    <span aria-hidden="true" class="relative flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-cyan-400 text-[11px] font-bold tracking-tight text-white shadow-lg shadow-indigo-500/30 ring-1 ring-white/20">
                        <span class="absolute inset-0 rounded-xl bg-gradient-to-br from-white/30 to-transparent opacity-60"></span>
                        <span class="relative">EBQ</span>
                    </span>
                    <span class="text-sm font-semibold tracking-[0.22em] text-white/85 uppercase">EBQ</span>
                </a>

                <nav aria-label="Primary" class="hidden items-center gap-8 text-sm text-white/70 md:flex">
                    <a href="#features" class="transition hover:text-white">Features</a>
                    <a href="#workflow" class="transition hover:text-white">Workflow</a>
                    <a href="#proof" class="transition hover:text-white">Results</a>
                    <a href="#faq" class="transition hover:text-white">FAQ</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="hidden rounded-full px-4 py-2 text-sm font-medium text-white/80 transition hover:bg-white/5 hover:text-white sm:inline-flex">
                        Sign in
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-slate-100">
                        Start free
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    </a>
                </div>
            </div>
        </header>

        <main id="main">
            {{-- Hero --}}
            <section class="mx-auto grid max-w-7xl items-center gap-12 px-6 pb-20 pt-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-16 lg:px-8 lg:pb-28 lg:pt-20">
                <div class="min-w-0 max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3.5 py-1.5 text-xs font-medium text-indigo-100 backdrop-blur sm:text-sm">
                        <span aria-hidden="true" class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                        </span>
                        Live rank tracking for 250k+ keywords daily
                    </div>

                    <h1 class="mt-6 text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl">
                        The SEO command center for teams that
                        <span class="bg-gradient-to-r from-white via-indigo-200 to-cyan-300 bg-clip-text text-transparent">measure what matters</span>.
                    </h1>

                    <p class="mt-6 max-w-xl text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                        EBQ unifies rankings, analytics, backlinks, and page performance into one workspace — so your team spends less time stitching dashboards and more time shipping the work that moves pipeline.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-semibold text-slate-950 shadow-xl shadow-indigo-500/10 transition hover:bg-slate-100">
                            Start your free trial
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                        <a href="#workflow" class="inline-flex items-center justify-center gap-2 rounded-full border border-white/15 bg-white/5 px-6 py-3 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/10">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
                            See how it works
                        </a>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-slate-400">
                        <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg> 14-day free trial</span>
                        <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg> No credit card required</span>
                        <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg> Cancel any time</span>
                    </div>
                </div>

                <div class="relative min-w-0">
                    <div aria-hidden="true" class="pointer-events-none absolute -inset-6 rounded-[2.5rem] bg-gradient-to-tr from-indigo-500/25 via-cyan-500/15 to-transparent blur-3xl"></div>

                    <div class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-slate-900/70 p-5 shadow-2xl shadow-slate-950/60 ring-1 ring-white/10 backdrop-blur sm:p-6">
                        <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-cyan-400 text-[11px] font-bold text-white ring-1 ring-white/20">EBQ</span>
                                <div class="min-w-0">
                                    <p class="truncate text-xs text-slate-400">Weekly growth pulse</p>
                                    <p class="truncate text-sm font-semibold text-white">acme.com — organic overview</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-400/15 px-2.5 py-1 text-xs font-semibold text-emerald-300 ring-1 ring-emerald-400/20">
                                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                                +12.4%
                            </span>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                                <p class="text-xs text-slate-400">Top keyword momentum</p>
                                <p class="mt-2 text-3xl font-semibold tracking-tight text-white">84</p>
                                <p class="mt-1 text-xs text-emerald-300">high-intent pages gained positions</p>
                            </div>
                            <div class="rounded-2xl bg-gradient-to-br from-indigo-500 to-cyan-500 p-4 text-white shadow-lg shadow-indigo-500/20 ring-1 ring-white/15">
                                <p class="text-xs text-white/80">Revenue opportunity</p>
                                <p class="mt-2 text-3xl font-semibold tracking-tight">$148k</p>
                                <p class="mt-1 text-xs text-white/85">monthly upside from priority cluster</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                            <div class="flex items-end justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs text-slate-400">Search visibility trend</p>
                                    <p class="mt-1 truncate text-sm font-semibold text-white">Steady improvement across core pages</p>
                                </div>
                                <p class="shrink-0 text-xs font-medium text-cyan-300">Last 90 days</p>
                            </div>

                            <div class="mt-5 flex h-28 items-end gap-1.5 sm:h-32" aria-hidden="true">
                                @foreach ([28, 34, 30, 42, 38, 46, 52, 48, 58, 54, 63, 70, 66, 78, 85, 92] as $h)
                                    <div class="flex-1 rounded-t-md bg-gradient-to-t from-indigo-500/30 via-indigo-400/60 to-cyan-300/90" style="height: {{ $h }}%"></div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-4 grid gap-2 sm:grid-cols-3">
                            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">GA4</p>
                                <p class="mt-1 text-sm font-semibold text-white">Traffic quality</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">GSC</p>
                                <p class="mt-1 text-sm font-semibold text-white">Query intent</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Backlinks</p>
                                <p class="mt-1 text-sm font-semibold text-white">Link health</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Stats strip --}}
            <section class="border-y border-white/5 bg-slate-950/50 backdrop-blur" aria-label="At a glance">
                <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
                    <dl class="grid grid-cols-2 gap-6 text-center sm:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-400">Keywords tracked</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white sm:text-4xl">250k+</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-400">Avg. visibility lift</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white sm:text-4xl">+38%</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-400">Reporting time saved</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white sm:text-4xl">-72%</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-400">Teams onboarded weekly</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white sm:text-4xl">120+</dd>
                        </div>
                    </dl>
                </div>
            </section>

            {{-- Features --}}
            <section id="features" class="bg-slate-50 py-24 text-slate-900 sm:py-28">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-indigo-600">Why teams choose EBQ</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">Beautiful reporting. Sharper prioritization. Faster SEO wins.</h2>
                        <p class="mt-5 text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">
                            Built for teams that need more than rankings alone. EBQ connects performance data to clear next steps so the right work gets attention first.
                        </p>
                    </div>

                    <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @php
                            $features = [
                                ['title' => 'Unified search intelligence', 'desc' => 'Bring rankings, page performance, backlinks, and analytics into one workspace your team actually uses every day.', 'tint' => 'bg-indigo-100 text-indigo-600', 'icon' => 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605'],
                                ['title' => 'Insights tied to revenue', 'desc' => 'Move beyond vanity metrics. See visibility changes tied to traffic quality, conversion potential, and strategic page impact.', 'tint' => 'bg-cyan-100 text-cyan-700', 'icon' => 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941'],
                                ['title' => 'Backlink audit on autopilot', 'desc' => 'Add a backlink and we visit the referring page, verify the link, and flag missing, mismatched, or nofollow changes.', 'tint' => 'bg-emerald-100 text-emerald-700', 'icon' => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244'],
                                ['title' => 'Executive-ready storytelling', 'desc' => 'Share polished dashboards and trend summaries that help stakeholders understand what changed, why it matters, and what to do next.', 'tint' => 'bg-rose-100 text-rose-600', 'icon' => 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h12M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-15 3.75h7.5'],
                                ['title' => 'Page-level performance', 'desc' => 'Drill into every tracked page to see which queries, clusters, and referring sources are driving movement — good or bad.', 'tint' => 'bg-amber-100 text-amber-700', 'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'],
                                ['title' => 'Scheduled growth reports', 'desc' => 'Ship recurring reports to clients and leadership with zero copy-paste. Every Monday morning, done.', 'tint' => 'bg-sky-100 text-sky-700', 'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                            ];
                        @endphp

                        @foreach ($features as $f)
                            <article class="group rounded-2xl border border-slate-200 bg-white p-7 shadow-sm shadow-slate-200/60 transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $f['tint'] }}">
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $f['icon'] }}" /></svg>
                                </div>
                                <h3 class="mt-5 text-lg font-semibold tracking-tight">{{ $f['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $f['desc'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Workflow --}}
            <section id="workflow" class="bg-white py-24 text-slate-900 sm:py-28">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-indigo-600">How it works</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">Go from messy spreadsheets to a clear growth plan in an afternoon.</h2>
                    </div>

                    <ol class="mt-14 grid gap-6 lg:grid-cols-3">
                        @php
                            $steps = [
                                ['n' => '01', 'title' => 'Connect your stack', 'desc' => 'One-click OAuth for Google Search Console and GA4. Add backlinks manually or in bulk. Invite teammates.'],
                                ['n' => '02', 'title' => 'Let EBQ do the legwork', 'desc' => 'We sync data daily, verify backlinks on the source page, and surface changes that actually matter.'],
                                ['n' => '03', 'title' => 'Report with confidence', 'desc' => 'Pre-built dashboards and scheduled growth reports keep stakeholders aligned without the copy-paste grind.'],
                            ];
                        @endphp
                        @foreach ($steps as $s)
                            <li class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-7 shadow-sm">
                                <span aria-hidden="true" class="absolute -right-2 -top-4 text-7xl font-semibold tracking-tighter text-slate-100">{{ $s['n'] }}</span>
                                <span class="relative text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600">Step {{ $s['n'] }}</span>
                                <h3 class="relative mt-3 text-xl font-semibold tracking-tight">{{ $s['title'] }}</h3>
                                <p class="relative mt-3 text-sm leading-6 text-slate-600">{{ $s['desc'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- Proof / testimonial --}}
            <section id="proof" class="bg-slate-50 py-24 text-slate-900 sm:py-28">
                <div class="mx-auto grid max-w-7xl gap-12 px-6 lg:grid-cols-[0.9fr_1.1fr] lg:gap-14 lg:px-8">
                    <div class="max-w-xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-indigo-600">Proof, not promises</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">Move from monitoring to momentum.</h2>
                        <p class="mt-5 text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">
                            EBQ helps teams spot high-value changes early, report with confidence, and keep leadership aligned around growth.
                        </p>

                        <dl class="mt-8 grid grid-cols-2 gap-4">
                            <div class="rounded-xl border border-slate-200 bg-white p-4">
                                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Time to insight</dt>
                                <dd class="mt-1 text-2xl font-semibold">3x faster</dd>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-4">
                                <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Forecast confidence</dt>
                                <dd class="mt-1 text-2xl font-semibold">91%</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="grid gap-5">
                        <figure class="relative overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-cyan-50 p-8 shadow-sm">
                            <svg class="absolute right-6 top-6 h-10 w-10 text-indigo-200" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 32 32" aria-hidden="true"><path d="M10 8c-3.3 0-6 2.7-6 6v10h10V14H8c0-1.1.9-2 2-2V8zm14 0c-3.3 0-6 2.7-6 6v10h10V14h-6c0-1.1.9-2 2-2V8z" /></svg>
                            <blockquote class="relative">
                                <p class="text-lg leading-8 text-slate-800 sm:text-xl sm:leading-9">
                                    EBQ gave our team a much more compelling way to understand organic growth. We stopped reacting to noise and started investing in the pages that moved pipeline.
                                </p>
                                <figcaption class="mt-6 flex items-center gap-3 text-sm">
                                    <span aria-hidden="true" class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-cyan-400 text-xs font-semibold text-white">GL</span>
                                    <span>
                                        <span class="block font-semibold text-slate-900">Growth Lead</span>
                                        <span class="block text-slate-500">B2B SaaS, Series B</span>
                                    </span>
                                </figcaption>
                            </blockquote>
                        </figure>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="rounded-2xl bg-slate-900 p-6 text-white">
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-400">Average setup time</p>
                                <p class="mt-4 text-4xl font-semibold">&lt; 10 min</p>
                                <p class="mt-3 text-sm leading-6 text-slate-300">Connect Search Console and GA4, pick your tracked websites, and you are reporting by lunchtime.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-6">
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Backlink audit coverage</p>
                                <p class="mt-4 text-4xl font-semibold text-slate-900">100%</p>
                                <p class="mt-3 text-sm leading-6 text-slate-600">Every backlink you add is verified against the live referring page — anchor, rel, and all.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- FAQ --}}
            <section id="faq" class="bg-white py-24 text-slate-900 sm:py-28">
                <div class="mx-auto max-w-4xl px-6 lg:px-8">
                    <div class="text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-indigo-600">FAQ</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Frequently asked questions</h2>
                    </div>

                    @php
                        $faqs = [
                            ['q' => 'How long does setup take?', 'a' => 'Most teams are live in under ten minutes. Connect Google Search Console and GA4, pick the websites you want to track, and EBQ handles the rest.'],
                            ['q' => 'Do you verify backlinks automatically?', 'a' => 'Yes. Every backlink you add — single or bulk — is audited: we fetch the referring page, confirm the link is present, and check whether the anchor text and dofollow status match what you recorded.'],
                            ['q' => 'Can I invite my team?', 'a' => 'Absolutely. Invite teammates or clients to any website with role-based access. Everyone sees the same truth.'],
                            ['q' => 'Is there a free trial?', 'a' => 'Yes — 14 days, no credit card required. You can cancel any time from your account settings.'],
                        ];
                    @endphp

                    <div class="mt-12 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-slate-50/60">
                        @foreach ($faqs as $faq)
                            <details class="group p-6">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-6 text-base font-semibold text-slate-900">
                                    {{ $faq['q'] }}
                                    <span aria-hidden="true" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-slate-500 ring-1 ring-slate-200 transition group-open:rotate-45 group-open:bg-indigo-600 group-open:text-white group-open:ring-indigo-600">
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    </span>
                                </summary>
                                <p class="mt-4 text-sm leading-6 text-slate-600">{{ $faq['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- CTA --}}
            <section id="get-started" class="relative isolate overflow-hidden bg-slate-950 py-24 text-white sm:py-28">
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_20%_20%,rgba(99,102,241,0.25),transparent_40%),radial-gradient(circle_at_80%_80%,rgba(14,165,233,0.2),transparent_40%)]"></div>
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="overflow-hidden rounded-[2rem] border border-white/10 bg-white/5 shadow-2xl shadow-slate-950/40 backdrop-blur">
                        <div class="grid gap-10 px-8 py-12 lg:grid-cols-[1fr_auto] lg:gap-12 lg:px-14 lg:py-16">
                            <div class="max-w-2xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-300">Ready when you are</p>
                                <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">Turn your search data into your next growth lever.</h2>
                                <p class="mt-5 text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                                    Start with a guided walkthrough and discover where rankings, analytics, and page insights can unlock your next wave of pipeline growth.
                                </p>
                            </div>

                            <div class="flex flex-col gap-3 self-center">
                                <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                                    Create your account
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                </a>
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-full border border-white/15 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                                    Existing customer sign in
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/10 bg-slate-950">
            <div class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
                    <div class="max-w-sm">
                        <a href="/" class="flex items-center gap-3" aria-label="EBQ home">
                            <span aria-hidden="true" class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-cyan-400 text-[11px] font-bold text-white ring-1 ring-white/20">EBQ</span>
                            <span class="text-sm font-semibold tracking-[0.22em] text-white uppercase">EBQ</span>
                        </a>
                        <p class="mt-4 text-sm leading-6 text-slate-400">
                            The SEO command center for teams that measure what matters. Rankings, backlinks, and pipeline in one workspace.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Product</h3>
                        <ul class="mt-4 space-y-3 text-sm text-slate-400">
                            <li><a href="#features" class="transition hover:text-white">Features</a></li>
                            <li><a href="#workflow" class="transition hover:text-white">Workflow</a></li>
                            <li><a href="#proof" class="transition hover:text-white">Results</a></li>
                            <li><a href="#faq" class="transition hover:text-white">FAQ</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Account</h3>
                        <ul class="mt-4 space-y-3 text-sm text-slate-400">
                            <li><a href="{{ route('register') }}" class="transition hover:text-white">Start free</a></li>
                            <li><a href="{{ route('login') }}" class="transition hover:text-white">Sign in</a></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Legal</h3>
                        <ul class="mt-4 space-y-3 text-sm text-slate-400">
                            <li><a href="#" class="transition hover:text-white">Privacy</a></li>
                            <li><a href="#" class="transition hover:text-white">Terms</a></li>
                            <li><a href="mailto:hello@ebq.app" class="transition hover:text-white">Contact</a></li>
                        </ul>
                    </div>
                </div>

                <div class="mt-12 flex flex-col gap-4 border-t border-white/10 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {{ date('Y') }} EBQ. Built for modern SEO and growth teams.</p>
                    <p>Made with care for operators who care about clarity.</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
