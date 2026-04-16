<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>EBQ | SEO Intelligence That Drives Growth</title>
    <meta
        name="description"
        content="EBQ helps teams unify search performance, keyword insights, and page-level analytics in one elegant SEO dashboard."
    >
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    @if (! app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-full bg-slate-950 text-white antialiased">
    <div class="relative isolate overflow-hidden">
        <div class="absolute inset-x-0 top-0 -z-10 h-[38rem] bg-[radial-gradient(circle_at_top,rgba(99,102,241,0.35),transparent_45%),radial-gradient(circle_at_20%_20%,rgba(14,165,233,0.18),transparent_30%),linear-gradient(180deg,#020617_0%,#0f172a_55%,#f8fafc_100%)]"></div>

        <header class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
            <a href="/" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15 backdrop-blur">EBQ</span>
                <span class="text-sm font-semibold tracking-[0.25em] text-white/80 uppercase">SEO Intelligence</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm text-white/70 md:flex">
                <a href="#features" class="transition hover:text-white">Features</a>
                <a href="#proof" class="transition hover:text-white">Results</a>
                <a href="#pricing" class="transition hover:text-white">Why EBQ</a>
            </nav>

            <div class="flex items-center gap-3">
                <a
                    href="{{ route('login') }}"
                    class="hidden rounded-full px-4 py-2 text-sm font-medium text-white/80 transition hover:bg-white/5 hover:text-white sm:inline-flex"
                >
                    Sign in
                </a>
                <a
                    href="{{ route('register') }}"
                    class="inline-flex items-center rounded-full bg-indigo-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-400"
                >
                    Start free trial
                </a>
            </div>
        </header>

        <main>
            <section class="mx-auto grid max-w-7xl gap-16 px-6 pb-24 pt-10 lg:grid-cols-[minmax(0,1fr)_30rem] lg:px-8 lg:pb-32 lg:pt-16">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-sm text-indigo-100 backdrop-blur">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        Trusted for daily SEO visibility tracking
                    </div>

                    <h1 class="mt-8 text-5xl font-semibold tracking-tight text-white sm:text-6xl lg:text-7xl">
                        Turn search data into
                        <span class="bg-gradient-to-r from-white via-indigo-200 to-cyan-300 bg-clip-text text-transparent">revenue-ready decisions</span>.
                    </h1>

                    <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300 sm:text-xl">
                        EBQ gives growth teams one elegant command center for rankings, page performance, and high-impact SEO opportunities so you can act faster and scale smarter.
                    </p>

                    <div class="mt-10 flex flex-col gap-4 sm:flex-row">
                        <a
                            href="{{ route('register') }}"
                            class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 text-sm font-semibold text-slate-950 shadow-2xl shadow-white/10 transition hover:bg-slate-100"
                        >
                            Book a product walkthrough
                        </a>
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-6 py-3 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/10"
                        >
                            Explore the dashboard
                        </a>
                    </div>

                    <dl class="mt-14 grid max-w-2xl grid-cols-1 gap-6 text-left sm:grid-cols-3">
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur">
                            <dt class="text-sm text-slate-400">Visibility Growth</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white">+38%</dd>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur">
                            <dt class="text-sm text-slate-400">Tracked Keywords</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white">250k+</dd>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur">
                            <dt class="text-sm text-slate-400">Reporting Time</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white">-72%</dd>
                        </div>
                    </dl>
                </div>

                <div class="relative">
                    <div class="absolute -inset-4 rounded-[2rem] bg-indigo-500/20 blur-3xl"></div>
                    <div class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-slate-900/80 p-6 shadow-2xl shadow-slate-950/50 ring-1 ring-white/10 backdrop-blur">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-slate-400">Organic performance snapshot</p>
                                <h2 class="mt-2 text-2xl font-semibold">Weekly Growth Pulse</h2>
                            </div>
                            <div class="rounded-full bg-emerald-400/15 px-3 py-1 text-sm font-medium text-emerald-300">
                                +12.4%
                            </div>
                        </div>

                        <div class="mt-8 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-3xl bg-white/5 p-5 ring-1 ring-white/10">
                                <p class="text-sm text-slate-400">Top keyword momentum</p>
                                <p class="mt-4 text-4xl font-semibold">84</p>
                                <p class="mt-2 text-sm text-emerald-300">High-intent pages gained positions this week</p>
                            </div>
                            <div class="rounded-3xl bg-gradient-to-br from-indigo-500 to-cyan-500 p-5 text-white shadow-lg shadow-indigo-500/25">
                                <p class="text-sm text-white/80">Revenue opportunity</p>
                                <p class="mt-4 text-4xl font-semibold">$148k</p>
                                <p class="mt-2 text-sm text-white/80">Estimated monthly upside from your priority cluster</p>
                            </div>
                        </div>

                        <div class="mt-6 rounded-3xl border border-white/10 bg-slate-950/70 p-5">
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-sm text-slate-400">Search visibility trend</p>
                                    <p class="mt-2 text-lg font-semibold text-white">Steady improvement across core pages</p>
                                </div>
                                <p class="text-sm font-medium text-cyan-300">Last 90 days</p>
                            </div>

                            <div class="mt-8 flex h-40 items-end gap-3">
                                <div class="w-full rounded-t-3xl bg-slate-800" style="height: 38%"></div>
                                <div class="w-full rounded-t-3xl bg-slate-700" style="height: 48%"></div>
                                <div class="w-full rounded-t-3xl bg-slate-600" style="height: 52%"></div>
                                <div class="w-full rounded-t-3xl bg-indigo-500/70" style="height: 68%"></div>
                                <div class="w-full rounded-t-3xl bg-indigo-400/80" style="height: 83%"></div>
                                <div class="w-full rounded-t-3xl bg-cyan-400/90" style="height: 92%"></div>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-sm text-slate-400">GA4</p>
                                <p class="mt-2 font-semibold text-white">Traffic quality</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-sm text-slate-400">GSC</p>
                                <p class="mt-2 font-semibold text-white">Query intent</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-sm text-slate-400">SEO</p>
                                <p class="mt-2 font-semibold text-white">Actionable fixes</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="bg-slate-50 py-24 text-slate-900">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-indigo-600">Why teams choose EBQ</p>
                        <h2 class="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">Beautiful reporting, sharper prioritization, faster SEO wins.</h2>
                        <p class="mt-6 text-lg leading-8 text-slate-600">
                            Built for teams that need more than rankings alone. EBQ connects performance data to clear next steps so the right work gets attention first.
                        </p>
                    </div>

                    <div class="mt-16 grid gap-6 lg:grid-cols-3">
                        <article class="rounded-[2rem] border border-slate-200 bg-white p-8 shadow-sm shadow-slate-200/60">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-600">01</div>
                            <h3 class="mt-6 text-2xl font-semibold">Unified search intelligence</h3>
                            <p class="mt-4 leading-7 text-slate-600">
                                Bring rankings, page performance, and analytics signals together in a single workspace your team can actually use every day.
                            </p>
                        </article>

                        <article class="rounded-[2rem] border border-slate-200 bg-white p-8 shadow-sm shadow-slate-200/60">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-100 text-cyan-700">02</div>
                            <h3 class="mt-6 text-2xl font-semibold">Insights with business context</h3>
                            <p class="mt-4 leading-7 text-slate-600">
                                Move beyond vanity metrics with visibility changes tied to conversion potential, traffic quality, and strategic page impact.
                            </p>
                        </article>

                        <article class="rounded-[2rem] border border-slate-200 bg-white p-8 shadow-sm shadow-slate-200/60">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">03</div>
                            <h3 class="mt-6 text-2xl font-semibold">Executive-ready storytelling</h3>
                            <p class="mt-4 leading-7 text-slate-600">
                                Share polished dashboards and trend summaries that help stakeholders understand what changed, why it matters, and what to do next.
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="proof" class="bg-white py-24 text-slate-900">
                <div class="mx-auto grid max-w-7xl gap-12 px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-indigo-600">Proof, not promises</p>
                        <h2 class="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">Your SEO team gets the clarity to move from monitoring to momentum.</h2>
                        <p class="mt-6 text-lg leading-8 text-slate-600">
                            EBQ helps teams spot high-value changes early, report with confidence, and keep leadership aligned around growth.
                        </p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="rounded-[2rem] bg-slate-900 p-8 text-white">
                            <p class="text-sm text-slate-400">Average time to insight</p>
                            <p class="mt-6 text-5xl font-semibold">3x faster</p>
                            <p class="mt-4 text-sm leading-6 text-slate-300">Less digging through disconnected tools and more time acting on the pages that matter most.</p>
                        </div>
                        <div class="rounded-[2rem] border border-slate-200 bg-slate-50 p-8">
                            <p class="text-sm text-slate-500">Forecast confidence</p>
                            <p class="mt-6 text-5xl font-semibold text-slate-900">91%</p>
                            <p class="mt-4 text-sm leading-6 text-slate-600">A clearer picture of where traffic gains are coming from and what to prioritize next.</p>
                        </div>
                        <blockquote class="sm:col-span-2 rounded-[2rem] border border-indigo-100 bg-indigo-50 p-8">
                            <p class="text-lg leading-8 text-slate-700">
                                "EBQ gave our team a much more compelling way to understand organic growth. We stopped reacting to noise and started investing in the pages that moved pipeline."
                            </p>
                            <footer class="mt-6 text-sm font-semibold text-indigo-700">Growth Lead, B2B SaaS team</footer>
                        </blockquote>
                    </div>
                </div>
            </section>

            <section id="pricing" class="bg-slate-950 py-24 text-white">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="overflow-hidden rounded-[2rem] border border-white/10 bg-white/5 shadow-2xl shadow-slate-950/40 backdrop-blur">
                        <div class="grid gap-10 px-8 py-10 lg:grid-cols-[1fr_auto] lg:px-12 lg:py-14">
                            <div class="max-w-2xl">
                                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300">Ready to scale smarter?</p>
                                <h2 class="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">See how EBQ can turn your search data into your next growth lever.</h2>
                                <p class="mt-6 text-lg leading-8 text-slate-300">
                                    Start with a guided walkthrough and discover where rankings, analytics, and page insights can unlock your next wave of pipeline growth.
                                </p>
                            </div>

                            <div class="flex flex-col gap-4 self-center">
                                <a
                                    href="{{ route('register') }}"
                                    class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100"
                                >
                                    Create your account
                                </a>
                                <a
                                    href="{{ route('login') }}"
                                    class="inline-flex items-center justify-center rounded-full border border-white/15 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                                >
                                    Existing customer sign in
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/10 bg-slate-950">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <p>&copy; {{ date('Y') }} EBQ. Built for modern SEO and growth teams.</p>
                <div class="flex items-center gap-5">
                    <a href="#features" class="transition hover:text-white">Features</a>
                    <a href="#proof" class="transition hover:text-white">Results</a>
                    <a href="{{ route('login') }}" class="transition hover:text-white">Sign in</a>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
