<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <title>EBQ - SEO Intelligence Platform</title>
    <meta name="description" content="EBQ helps growth teams prioritize SEO work with clear dashboards, backlink monitoring, and automated reporting.">
    <link rel="canonical" href="{{ url('/') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="EBQ - SEO Intelligence Platform">
    <meta property="og:description" content="Track rankings, backlinks, and content impact in one modern platform built for growth teams.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:site_name" content="EBQ">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="EBQ - SEO Intelligence Platform">
    <meta name="twitter:description" content="Track rankings, backlinks, and content impact in one modern platform built for growth teams.">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @if (! app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-full bg-slate-950 font-sans text-slate-100 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-900">Skip to content</a>

    <div class="relative overflow-x-clip">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[38rem] bg-[radial-gradient(circle_at_20%_0,rgba(59,130,246,0.25),transparent_45%),radial-gradient(circle_at_80%_10%,rgba(99,102,241,0.3),transparent_40%)]"></div>

        <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/80 backdrop-blur">
            <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
                <a href="/" class="flex items-center gap-3" aria-label="EBQ home">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-cyan-400 text-xs font-bold text-white">EBQ</span>
                    <span class="text-sm font-semibold uppercase tracking-[0.2em] text-white">EBQ</span>
                </a>

                <nav aria-label="Primary" class="hidden items-center gap-8 text-sm font-medium text-slate-300 md:flex">
                    <a href="#features" class="transition hover:text-white">Features</a>
                    <a href="#how" class="transition hover:text-white">How it works</a>
                    <a href="#results" class="transition hover:text-white">Results</a>
                    <a href="#faq" class="transition hover:text-white">FAQ</a>
                </nav>

                <div class="flex items-center gap-3">
                    <a href="{{ route('login') }}" class="hidden rounded-md px-4 py-2 text-sm font-medium text-slate-200 transition hover:bg-white/10 sm:inline-flex">Sign in</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-slate-200">Start free</a>
                </div>
            </div>
        </header>

        <main id="main">
            <section class="mx-auto grid max-w-7xl gap-12 px-6 pb-20 pt-16 lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8 lg:pb-28 lg:pt-24">
                <div class="max-w-2xl">
                    <p class="inline-flex rounded-full border border-indigo-300/30 bg-indigo-500/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-indigo-200">
                        Built for modern growth teams
                    </p>
                    <h1 class="mt-6 text-4xl font-semibold leading-tight text-white sm:text-5xl lg:text-6xl">
                        A production-grade SEO workspace your whole team can trust.
                    </h1>
                    <p class="mt-6 text-base leading-8 text-slate-300 sm:text-lg">
                        EBQ combines rankings, backlinks, analytics, and page-level insights into one clear platform so your team can move faster with confidence.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-200">
                            Start your free trial
                        </a>
                        <a href="#how" class="inline-flex items-center justify-center rounded-md border border-white/20 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                            See how it works
                        </a>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-300">
                        <span>14-day trial</span>
                        <span>No credit card needed</span>
                        <span>Cancel any time</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-6 shadow-2xl">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-xl border border-white/10 bg-slate-950/70 p-4">
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Tracked keywords</p>
                            <p class="mt-2 text-3xl font-semibold text-white">250k+</p>
                        </div>
                        <div class="rounded-xl border border-indigo-300/30 bg-indigo-500/10 p-4">
                            <p class="text-xs font-medium uppercase tracking-wider text-indigo-200">Visibility lift</p>
                            <p class="mt-2 text-3xl font-semibold text-white">+38%</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/70 p-4">
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Reports automated</p>
                            <p class="mt-2 text-3xl font-semibold text-white">72%</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/70 p-4">
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Backlink checks</p>
                            <p class="mt-2 text-3xl font-semibold text-white">100%</p>
                        </div>
                    </div>
                    <div class="mt-6 rounded-xl border border-white/10 bg-slate-950/70 p-4">
                        <p class="text-sm font-medium text-slate-300">Search visibility trend (last 90 days)</p>
                        <div class="mt-4 flex h-28 items-end gap-1.5" aria-hidden="true">
                            @foreach ([22, 30, 28, 36, 40, 46, 44, 55, 58, 63, 67, 74] as $h)
                                <div class="flex-1 rounded-t bg-gradient-to-t from-indigo-500/30 via-indigo-400/60 to-cyan-300/90" style="height: {{ $h }}%"></div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="bg-white py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Everything your SEO operation needs, in one place</h2>
                        <p class="mt-4 text-base leading-7 text-slate-600 sm:text-lg">
                            The layout is designed for clarity: clear hierarchy, readable copy, and focused modules your team can use daily.
                        </p>
                    </div>

                    @php
                        $features = [
                            ['title' => 'Unified dashboard', 'desc' => 'See rankings, traffic quality, and page performance without jumping between tools.'],
                            ['title' => 'Backlink monitoring', 'desc' => 'Track link status, anchor updates, and dofollow changes automatically.'],
                            ['title' => 'Actionable prioritization', 'desc' => 'Spot high-impact pages and keywords to focus your next sprint.'],
                            ['title' => 'Executive reporting', 'desc' => 'Generate polished reports that communicate impact in plain language.'],
                            ['title' => 'Daily data sync', 'desc' => 'Keep your team aligned with up-to-date, trustworthy metrics.'],
                            ['title' => 'Team collaboration', 'desc' => 'Invite internal teams or clients with clear access control and shared visibility.'],
                        ];
                    @endphp

                    <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($features as $feature)
                            <article class="rounded-xl border border-slate-200 bg-slate-50 p-6">
                                <h3 class="text-lg font-semibold text-slate-900">{{ $feature['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $feature['desc'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="how" class="bg-slate-100 py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">How it works</h2>
                    </div>

                    @php
                        $steps = [
                            ['title' => 'Connect sources', 'desc' => 'Connect Search Console and GA4, then add websites and pages to track.'],
                            ['title' => 'Monitor movement', 'desc' => 'EBQ refreshes data daily and flags meaningful changes early.'],
                            ['title' => 'Report outcomes', 'desc' => 'Share clear updates with your team and leadership every week.'],
                        ];
                    @endphp

                    <ol class="mt-12 grid gap-5 lg:grid-cols-3">
                        @foreach ($steps as $index => $step)
                            <li class="rounded-xl border border-slate-200 bg-white p-6">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600">Step {{ $index + 1 }}</p>
                                <h3 class="mt-3 text-xl font-semibold text-slate-900">{{ $step['title'] }}</h3>
                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $step['desc'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            <section id="results" class="bg-white py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid gap-8 lg:grid-cols-3">
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-7">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Time to insight</p>
                            <p class="mt-3 text-4xl font-semibold text-slate-900">3x faster</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-7">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Reporting effort</p>
                            <p class="mt-3 text-4xl font-semibold text-slate-900">-72%</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-7">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Forecast confidence</p>
                            <p class="mt-3 text-4xl font-semibold text-slate-900">91%</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="faq" class="bg-slate-100 py-20 text-slate-900 sm:py-24">
                <div class="mx-auto max-w-4xl px-6 lg:px-8">
                    <div class="text-center">
                        <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Frequently asked questions</h2>
                    </div>

                    @php
                        $faqs = [
                            ['q' => 'How long does setup take?', 'a' => 'Most teams are live in under ten minutes.'],
                            ['q' => 'Can I invite my team?', 'a' => 'Yes. You can invite teammates or clients with controlled access.'],
                            ['q' => 'Do you support backlink validation?', 'a' => 'Yes. EBQ checks backlink status and anchor consistency.'],
                            ['q' => 'Is there a free trial?', 'a' => 'Yes. You get 14 days free, no credit card required.'],
                        ];
                    @endphp

                    <div class="mt-10 divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white">
                        @foreach ($faqs as $faq)
                            <details class="p-6">
                                <summary class="cursor-pointer list-none text-base font-semibold text-slate-900">{{ $faq['q'] }}</summary>
                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $faq['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="bg-slate-900 py-20 text-white sm:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="rounded-2xl border border-white/15 bg-slate-800 p-8 text-center sm:p-12">
                        <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Ready to clean up your SEO workflow?</h2>
                        <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                            Replace confusing dashboards with a clear, reliable command center your team actually uses.
                        </p>
                        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-slate-200">
                                Create account
                            </a>
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-md border border-white/25 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                                Sign in
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/10 bg-slate-950">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-10 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <p>&copy; {{ date('Y') }} EBQ. All rights reserved.</p>
                <p>Built for SEO and growth operators.</p>
            </div>
        </footer>
    </div>
</body>
</html>
