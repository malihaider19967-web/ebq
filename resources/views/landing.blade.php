<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">

    @include('partials.favicon-links')

    <title>EBQ - SEO Operations Platform</title>
    <meta name="description" content="EBQ helps growth teams manage SEO with clear dashboards, backlink verification, and reporting automation.">
    <link rel="canonical" href="{{ url('/') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="EBQ - SEO Operations Platform">
    <meta property="og:description" content="One clean workspace for rankings, backlinks, and performance insights.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:site_name" content="EBQ">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="EBQ - SEO Operations Platform">
    <meta name="twitter:description" content="One clean workspace for rankings, backlinks, and performance insights.">

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
                <a href="/" class="flex items-center gap-3" aria-label="EBQ home">
                    <img src="{{ asset('logo.png') }}" alt="" aria-hidden="true" width="36" height="36" class="h-9 w-9 rounded-lg object-cover ring-1 ring-white/25">
                </a>

                <nav aria-label="Primary" class="hidden items-center gap-8 text-sm font-medium text-slate-100 md:flex">
                    <a href="{{ route('features') }}" class="transition hover:text-indigo-200">Features</a>
                    <a href="{{ route('pricing') }}" class="transition hover:text-indigo-200">Pricing</a>
                    <a href="#wordpress" class="transition hover:text-indigo-200">WordPress</a>
                    <a href="#workflow" class="transition hover:text-indigo-200">Workflow</a>
                    <a href="#faq" class="transition hover:text-indigo-200">FAQ</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="hidden rounded-md px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 sm:inline-flex">Sign in</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">Start free</a>
                </div>
            </div>
        </header>

        <main id="main">
            <section class="mx-auto grid max-w-7xl gap-12 px-6 pb-20 pt-14 lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8 lg:pb-28 lg:pt-20">
                <div class="max-w-2xl">
                    <p class="inline-flex items-center rounded-full border border-indigo-200/40 bg-indigo-500/15 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-100">
                        Clear SEO operations, no noise
                    </p>
                    <h1 class="mt-6 text-4xl font-semibold tracking-tight text-white sm:text-5xl lg:text-6xl">
                        The landing page your product deserves.
                    </h1>
                    <p class="mt-6 text-base leading-8 text-slate-100 sm:text-lg">
                        EBQ helps teams replace scattered SEO reporting with one readable workspace for rankings, backlinks, and growth decisions.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                            Start free trial
                        </a>
                        <a href="#workflow" class="inline-flex items-center justify-center rounded-md border border-white/25 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                            See workflow
                        </a>
                    </div>
                    <div class="mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-100">
                        <span>14-day trial</span>
                        <span>No credit card</span>
                        <span>Cancel anytime</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/15 bg-slate-900 p-6 shadow-2xl">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <article class="rounded-xl border border-white/10 bg-slate-950 p-4">
                            <p class="text-xs uppercase tracking-wider text-slate-300">Keywords tracked</p>
                            <p class="mt-2 text-3xl font-semibold text-white">250k+</p>
                        </article>
                        <article class="rounded-xl border border-indigo-300/40 bg-indigo-500/20 p-4">
                            <p class="text-xs uppercase tracking-wider text-indigo-100">Visibility lift</p>
                            <p class="mt-2 text-3xl font-semibold text-white">+38%</p>
                        </article>
                    </div>
                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-100">Search visibility trend</p>
                            <p class="text-xs text-cyan-300">Last 90 days</p>
                        </div>
                        <div class="mt-4 flex h-28 items-end gap-1.5" aria-hidden="true">
                            @foreach ([22, 30, 28, 36, 44, 42, 51, 60, 58, 66, 72, 80] as $h)
                                <span class="flex-1 rounded-t-md bg-gradient-to-t from-indigo-500/40 via-indigo-400/70 to-cyan-300" style="height: {{ $h }}%"></span>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                        <div class="rounded-lg border border-white/10 bg-white/5 p-3 text-center text-xs font-medium text-slate-100">GA4 quality</div>
                        <div class="rounded-lg border border-white/10 bg-white/5 p-3 text-center text-xs font-medium text-slate-100">GSC intent</div>
                        <div class="rounded-lg border border-white/10 bg-white/5 p-3 text-center text-xs font-medium text-slate-100">Backlink health</div>
                    </div>
                </div>
            </section>

            <section class="border-y border-slate-200 bg-white py-10 text-slate-900">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <dl class="grid grid-cols-2 gap-6 text-center sm:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Tracked keywords</dt>
                            <dd class="mt-2 text-3xl font-semibold">250k+</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Average lift</dt>
                            <dd class="mt-2 text-3xl font-semibold">+38%</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Reporting time saved</dt>
                            <dd class="mt-2 text-3xl font-semibold">-72%</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">Weekly onboardings</dt>
                            <dd class="mt-2 text-3xl font-semibold">120+</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section id="features" class="bg-slate-50 py-24 text-slate-900 sm:py-28">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">Features</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Simple layout, strong readability</h2>
                        <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">
                            No cluttered UI. Just clear blocks with strong contrast and clear copy that is easy to scan.
                        </p>
                    </div>

                    @php
                        $features = [
                            ['title' => 'Unified dashboard', 'desc' => 'Track rankings, backlinks, and page performance in one place.'],
                            ['title' => 'Backlink verification', 'desc' => 'Automatically confirm link presence, anchor text, and rel attributes.'],
                            ['title' => 'Page-level insights', 'desc' => 'See which pages and queries are moving your pipeline.'],
                            ['title' => 'Executive reporting', 'desc' => 'Send clean, readable reports without manual rework.'],
                            ['title' => 'Daily sync', 'desc' => 'Keep data fresh so your team always works from current signals.'],
                            ['title' => 'Team collaboration', 'desc' => 'Invite clients and stakeholders with shared visibility.'],
                        ];
                    @endphp

                    <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($features as $feature)
                            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                <h3 class="text-lg font-semibold">{{ $feature['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-700">{{ $feature['desc'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="workflow" class="bg-white py-24 text-slate-900 sm:py-28">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">Workflow</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Three steps to cleaner SEO operations</h2>
                    </div>

                    @php
                        $steps = [
                            ['title' => 'Connect your data', 'desc' => 'Connect Search Console and GA4 in minutes.'],
                            ['title' => 'Monitor changes', 'desc' => 'Track movement daily and focus on meaningful updates.'],
                            ['title' => 'Share outcomes', 'desc' => 'Deliver clear updates to leadership and clients.'],
                        ];
                    @endphp

                    <ol class="mt-14 grid gap-6 lg:grid-cols-3">
                        @foreach ($steps as $index => $step)
                            <li class="rounded-xl border border-slate-200 bg-slate-50 p-6">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600">Step {{ $index + 1 }}</p>
                                <h3 class="mt-3 text-xl font-semibold">{{ $step['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-700">{{ $step['desc'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            <section id="results" class="bg-slate-50 py-24 text-slate-900 sm:py-28">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="grid gap-6 lg:grid-cols-3">
                        <article class="rounded-xl border border-slate-200 bg-white p-7">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Time to insight</p>
                            <p class="mt-4 text-4xl font-semibold">3x faster</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-7">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Forecast confidence</p>
                            <p class="mt-4 text-4xl font-semibold">91%</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-7">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Average setup time</p>
                            <p class="mt-4 text-4xl font-semibold">&lt; 10 min</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="wordpress" class="bg-slate-50 py-24 text-slate-900 sm:py-28">
                <div class="mx-auto grid max-w-7xl gap-12 px-6 lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8">
                    <div>
                        <p class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700">
                            WordPress plugin · Beta
                        </p>
                        <h2 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">SEO intelligence, right inside Gutenberg</h2>
                        <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">
                            Editors see rank, 30-day clicks, cannibalization warnings, and striking-distance flags while writing. The WordPress dashboard surfaces insight counts. No tab switching.
                        </p>
                        <ul class="mt-6 space-y-3 text-sm text-slate-700">
                            <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span>Gutenberg sidebar with rank, clicks, and cannibalization warnings.</span></li>
                            <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span>Posts-list column with 30-day clicks and striking-distance badges.</span></li>
                            <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span>WordPress dashboard widget with deep-links into EBQ Reports.</span></li>
                            <li class="flex gap-3"><span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-indigo-500"></span><span>One-click challenge-response verification — no credentials in browser JS.</span></li>
                        </ul>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">
                                Sign in to download plugin
                            </a>
                            <a href="{{ route('features') }}#wordpress" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">See all plugin features</a>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">WP 6.0+ · PHP 8.1+ · Requires an EBQ account to activate.</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
                        <div class="flex items-center gap-2 border-b border-slate-200 pb-3 text-xs font-semibold text-slate-500">
                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            <span class="ml-2">EBQ SEO · Gutenberg sidebar</span>
                        </div>
                        <div class="mt-4 space-y-3 text-xs">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Search performance · 30d</p>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <div class="rounded bg-white px-2 py-1.5 ring-1 ring-slate-200"><span class="block text-[9px] uppercase text-slate-400">Clicks</span><span class="tabular-nums font-bold text-slate-900">1,284</span></div>
                                    <div class="rounded bg-white px-2 py-1.5 ring-1 ring-slate-200"><span class="block text-[9px] uppercase text-slate-400">Avg pos</span><span class="tabular-nums font-bold text-slate-900">6.4</span></div>
                                </div>
                            </div>
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700">Cannibalization</p>
                                <p class="mt-1 text-[11px] text-amber-900">"best seo tools" is split between this page and <span class="font-semibold">/blog/seo-tools-guide</span>. Consolidate.</p>
                            </div>
                            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Striking distance</p>
                                <p class="mt-1 text-[11px] text-indigo-900">3 queries at positions 5–20 with below-curve CTR. Tighten title + meta.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="faq" class="bg-white py-24 text-slate-900 sm:py-28">
                <div class="mx-auto max-w-4xl px-6 lg:px-8">
                    <div class="text-center">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">FAQ</p>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Questions before you switch</h2>
                    </div>

                    @php
                        $faqs = [
                            ['q' => 'How long does setup take?', 'a' => 'Most teams are live in less than ten minutes.'],
                            ['q' => 'Do you verify backlinks?', 'a' => 'Yes. EBQ checks presence, anchor text, and rel attributes.'],
                            ['q' => 'Can I invite team members?', 'a' => 'Yes. Invite teammates or clients with shared access.'],
                            ['q' => 'Do you offer a free trial?', 'a' => 'Yes. 14 days free with no credit card required.'],
                        ];
                    @endphp

                    <div class="mt-12 divide-y divide-slate-200 rounded-xl border border-slate-200 bg-slate-50">
                        @foreach ($faqs as $faq)
                            <details class="p-6">
                                <summary class="cursor-pointer list-none text-base font-semibold text-slate-900">{{ $faq['q'] }}</summary>
                                <p class="mt-3 text-sm leading-6 text-slate-700">{{ $faq['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="bg-slate-950 py-24 text-white sm:py-28">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="rounded-2xl border border-white/15 bg-slate-900 p-10 text-center">
                        <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Ready to run SEO with a cleaner system?</h2>
                        <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-100 sm:text-lg">
                            Start your free trial and move your team from cluttered reporting to consistent execution.
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
                        <img src="{{ asset('logo.png') }}" alt="" aria-hidden="true" width="36" height="36" class="h-9 w-9 rounded-lg object-cover ring-1 ring-white/25">
                    </a>
                    <p class="mt-3 text-slate-400">SEO workspace for teams that ship.</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Product</p>
                    <ul class="mt-3 space-y-2">
                        <li><a class="hover:text-indigo-200" href="{{ route('features') }}">Features</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('pricing') }}">Pricing</a></li>
                        <li><a class="hover:text-indigo-200" href="#wordpress">WordPress plugin</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Company</p>
                    <ul class="mt-3 space-y-2">
                        <li><a class="hover:text-indigo-200" href="mailto:hello@ebq.io">Contact</a></li>
                        <li><a class="hover:text-indigo-200" href="#faq">FAQ</a></li>
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
