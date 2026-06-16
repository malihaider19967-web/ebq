<x-marketing.page
    title="#1 Free SEO Analysis Tool With Detailed Audits"
    description="Analyze your website with the #1 free SEO audit tool. Get detailed tech insights, live GSC data, and actionable fixes to scale your search traffic."
>
    {{-- Page-specific structured data: the product itself. --}}
    <x-slot:schema>
        @php
            $landingSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => 'EBQ',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web, WordPress',
                'url' => route('landing'),
                'description' => 'Free SEO analysis and audit tool: detailed technical insights, live Google Search Console data, rank tracking, backlinks, and actionable fixes.',
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($landingSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    </x-slot:schema>

    {{-- ── Hero ──────────────────────────────────────────────── --}}
    <section class="relative">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[28rem] bg-[radial-gradient(ellipse_at_top,rgba(99,102,241,0.08),transparent_60%)]"></div>

        <div class="mx-auto max-w-4xl px-6 pb-20 pt-16 text-center lg:px-8 lg:pb-28 lg:pt-24">
            {{-- ── Hero copy ─────────────────────────────────── --}}
            <a href="{{ route('features') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                New: Anomaly alerts and backlink impact
                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>

            <h1 class="mx-auto mt-6 max-w-3xl text-balance text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                The SEO command center for teams that ship.
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-balance text-[17px] leading-8 text-slate-600">
                Unify Search Console, Analytics, ranking, audits, and backlinks into one quiet workspace. EBQ tells you what to fix this week, what to ship next, and what changed after release.
            </p>

            {{-- ── Free tools ── --}}
            <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                <span class="text-xs font-medium text-slate-400">Free tools:</span>
                <a href="{{ route('tools.pagespeed') }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-indigo-300 hover:text-indigo-700">
                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                    PageSpeed Test
                </a>
                <a href="{{ route('tools.audit') }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-indigo-300 hover:text-indigo-700">
                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
                    SEO Audit
                </a>
                <a href="{{ route('tools.rank-tracker') }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-indigo-300 hover:text-indigo-700">
                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    Rank Checker
                </a>
                <a href="{{ route('tools.keyword-volume') }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-indigo-300 hover:text-indigo-700">
                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                    Volume Checker
                </a>
            </div>

            {{-- ── Hero: free instant audit (full-width search bar) ── --}}
            <div class="relative mx-auto mt-10 max-w-3xl">
                {{-- Soft indigo glow behind the bar --}}
                <div aria-hidden="true" class="pointer-events-none absolute -inset-x-8 -inset-y-10 -z-10 bg-[radial-gradient(55%_60%_at_50%_0%,rgba(99,102,241,0.20),transparent_70%)] blur-2xl"></div>

                <form id="guest-audit-form" class="text-left" data-action="{{ route('guest-audit.store') }}" novalidate>
                    {{-- One inline bar on desktop; stacks on mobile. --}}
                    <div class="flex flex-col rounded-[20px] bg-white p-2 shadow-[0_30px_70px_-28px_rgba(15,23,42,0.30)] ring-1 ring-slate-200/80 transition focus-within:ring-2 focus-within:ring-indigo-500/70 sm:flex-row sm:items-center sm:divide-x sm:divide-slate-200/70 divide-y divide-slate-100 sm:divide-y-0">
                        {{-- URL (dominant) --}}
                        <div class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2.5">
                            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-inset ring-indigo-100">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12a8.964 8.964 0 0 1-1.318 4.682M12 21a8.997 8.997 0 0 1-7.843-4.582" /></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <label for="ga-url" class="block text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">Page URL</label>
                                <input id="ga-url" name="url" type="text" inputmode="url" autocomplete="url" autofocus required
                                    placeholder="yourwebsite.com/page"
                                    class="w-full border-0 bg-transparent p-0 text-[15px] font-medium text-slate-900 placeholder:font-normal placeholder:text-slate-400 focus:outline-none focus:ring-0">
                            </div>
                        </div>

                        {{-- Keyword --}}
                        <div class="flex items-center gap-3 px-3 py-2.5 sm:w-52">
                            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <label for="ga-keyword" class="block text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">Keyword</label>
                                <input id="ga-keyword" name="keyword" type="text" required maxlength="200"
                                    placeholder="best seo tools"
                                    class="w-full border-0 bg-transparent p-0 text-[15px] font-medium text-slate-900 placeholder:font-normal placeholder:text-slate-400 focus:outline-none focus:ring-0">
                            </div>
                        </div>

                        {{-- Country (SERP gl) --}}
                        <div class="flex items-center gap-3 px-3 py-2.5 sm:w-44">
                            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <label for="ga-country" class="block text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">Country</label>
                                <select id="ga-country" name="country"
                                    class="-ml-0.5 w-full border-0 bg-transparent p-0 text-[15px] font-medium text-slate-900 focus:outline-none focus:ring-0">
                                    <option value="">Auto-detect</option>
                                    @foreach (\App\Support\Audit\SerpGlCatalog::selectOptions() as $code => $label)
                                        <option value="{{ $code }}" @selected($code === 'us')>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Submit --}}
                        <div class="pt-2 sm:pl-2 sm:pt-0">
                            <button type="submit" id="ga-submit"
                                class="group inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 px-6 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:from-indigo-500 hover:to-violet-500 hover:shadow-indigo-600/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                                <svg id="ga-spinner" class="hidden h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                <span id="ga-label">Run free audit</span>
                                <svg id="ga-arrow" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5l7.5 7.5-7.5 7.5M21 12H3" /></svg>
                            </button>
                        </div>
                    </div>

                    @if (\App\Support\Recaptcha::isEnabled())
                        {{-- Captcha lives here for the 1st audit; the JS relocates it into the
                             email modal for the 2nd audit so it's never hidden behind the popup. --}}
                        <div id="ga-captcha-hero-slot" class="mt-4 flex justify-center">
                            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                        </div>
                    @endif

                    <p id="ga-error" role="alert" class="mx-auto mt-4 hidden max-w-md rounded-lg bg-rose-50 px-3 py-2 text-center text-[13px] font-medium text-rose-700 ring-1 ring-rose-100"></p>

                    <p class="mt-5 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5 text-emerald-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>Free</span>
                        <span class="text-slate-300">·</span>
                        <span>No signup</span>
                        <span class="text-slate-300">·</span>
                        <span>No credit card</span>
                        <span class="text-slate-300">—</span>
                        <a href="{{ route('register') }}" class="font-medium text-indigo-600 underline-offset-2 transition hover:text-indigo-700 hover:underline">or start a free trial →</a>
                    </p>
                </form>

                {{-- Confirmation shown after the email-gated (2nd) audit — report goes by email. --}}
                <div id="ga-success" class="hidden rounded-2xl border border-emerald-200 bg-white p-8 text-center shadow-[0_30px_70px_-28px_rgba(15,23,42,0.30)]">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                    </span>
                    <h3 class="mt-5 text-lg font-semibold text-slate-900">Check your inbox</h3>
                    <p id="ga-success-msg" class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">We’ve emailed your audit. It lands in a minute.</p>
                    <a href="{{ route('register') }}" class="mt-6 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Create a free account for unlimited audits →
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Name + email modal — shown on the 2nd audit (require:email) ── --}}
    <div id="ga-email-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div id="ga-email-backdrop" class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
        <div role="dialog" aria-modal="true" aria-labelledby="ga-email-title" class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5">
            <div class="px-7 pt-7">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-inset ring-indigo-100">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                </span>
                <h2 id="ga-email-title" class="mt-4 text-xl font-semibold tracking-tight text-slate-900">We’ll email you this audit</h2>
                <p id="ga-email-modal-msg" class="mt-2 text-sm leading-6 text-slate-600">This one’s on us — tell us where to send your report and we’ll deliver it to your inbox in about a minute.</p>
            </div>
            <form id="ga-email-form" class="px-7 pb-7 pt-5" novalidate>
                <div class="space-y-3">
                    <div>
                        <label for="ga-name" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Your name</label>
                        <input id="ga-name" name="name" type="text" autocomplete="name" maxlength="120" required
                            placeholder="Jane Doe"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="ga-email" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Email address</label>
                        <input id="ga-email" name="email" type="email" autocomplete="email" inputmode="email" required
                            placeholder="you@company.com"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>
                @if (\App\Support\Recaptcha::isEnabled())
                    {{-- The hero captcha widget is moved in here while the modal is open. --}}
                    <div id="ga-captcha-modal-slot" class="mt-4 flex justify-center"></div>
                @endif
                <p id="ga-email-error" role="alert" class="mt-3 hidden text-[13px] font-medium text-rose-600"></p>
                <div class="mt-5 flex flex-col gap-2 sm:flex-row-reverse">
                    <button type="submit" id="ga-email-submit"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:from-indigo-500 hover:to-violet-500 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg id="ga-email-spinner" class="hidden h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span id="ga-email-label">Email me my audit</span>
                    </button>
                    <button type="button" id="ga-email-cancel" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                        Cancel
                    </button>
                </div>
                <p class="mt-3 text-center text-xs text-slate-400">We’ll only use it to send your report and occasional product tips.</p>
            </form>
        </div>
    </div>

    {{-- ── Signup gate modal — shown on the 3rd audit (require:signup) ── --}}
    <div id="ga-signup-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div id="ga-signup-backdrop" class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
        <div role="dialog" aria-modal="true" aria-labelledby="ga-signup-title" class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5">
            <div class="bg-gradient-to-br from-indigo-600 to-violet-600 px-7 py-6 text-white">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-100">Keep auditing — free</p>
                <h2 id="ga-signup-title" class="mt-2 text-2xl font-semibold tracking-tight">Create your free account</h2>
            </div>
            <div class="px-7 py-6">
                <p id="ga-signup-msg" class="text-sm leading-6 text-slate-600">You’ve used your free audits. Create a free account to keep going — no credit card required.</p>
                <ul class="mt-5 space-y-2.5 text-sm text-slate-700">
                    @foreach ([
                        'Unlimited page audits — on-page, keyword & competitor benchmarks',
                        'Connect Search Console + Analytics for live keyword positions & click data',
                        'Core Web Vitals, rank tracking, and ranked fixes across your whole site',
                        'Free plan covers one website · no credit card',
                    ] as $perk)
                        <li class="flex items-start gap-2.5">
                            <svg class="mt-0.5 h-4 w-4 flex-none text-emerald-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                            <span>{{ $perk }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-7 flex flex-col gap-2 sm:flex-row">
                    <a href="{{ route('register') }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Create free account →
                    </a>
                    <button type="button" id="ga-signup-close" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                        Maybe later
                    </button>
                </div>
                <p class="mt-3 text-center text-xs text-slate-400">Already have an account? <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline">Sign in</a></p>
            </div>
        </div>
    </div>

    {{-- ── Logo strip ───────────────────────────────────────── --}}
    <section class="border-y border-slate-200 bg-slate-50/60 py-10">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <p class="text-center text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Connects with the tools you already trust</p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-4 text-sm font-medium text-slate-400">
                <span>Google Search Console</span>
                <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:block"></span>
                <span>Google Analytics 4</span>
                <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:block"></span>
                <span>Google Indexing API</span>
                <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:block"></span>
                <span>WordPress</span>
                <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:block"></span>
                <span>SERP data</span>
                <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:block"></span>
                <span>Core Web Vitals</span>
            </div>
        </div>
    </section>

    {{-- ── Three benefits ───────────────────────────────────── --}}
    <section class="bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Built for SEO operators</p>
                <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">A workflow, not another dashboard.</h2>
                <p class="mt-4 text-base leading-7 text-slate-600">EBQ replaces tab-switching with a single decision surface. Every signal points to an action, every action measures itself.</p>
            </div>

            {{-- ── Intro video ──────────────────────────────────── --}}
            <div class="relative mx-auto mt-12 max-w-4xl">
                <div aria-hidden="true" class="pointer-events-none absolute -inset-x-6 -inset-y-6 -z-10 rounded-[28px] bg-gradient-to-b from-slate-100 to-transparent"></div>
                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-900 shadow-[0_24px_60px_-24px_rgba(15,23,42,0.18)]" style="padding-top:56.25%">
                    <iframe
                        class="absolute inset-0 h-full w-full"
                        src="https://www.youtube-nocookie.com/embed/Rzme7QvSbLE"
                        title="EBQ intro video"
                        loading="lazy"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen></iframe>
                </div>
            </div>

            <div class="mx-auto mt-14 grid max-w-5xl gap-px overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 sm:grid-cols-3">
                @foreach ([
                    ['icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z', 'title' => 'Spot what changed', 'desc' => 'Anomaly detection, content decay, and indexing regressions surface in seconds — not in your next monthly review.'],
                    ['icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z', 'title' => 'Prioritize like a PM', 'desc' => 'Striking-distance and cannibalization queries are scored by impact and ranked. Your backlog stops guessing.'],
                    ['icon' => 'M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12z', 'title' => 'Prove what shipped', 'desc' => 'Every fix is tracked against rank, click, and CWV deltas. Reports auto-attach the evidence stakeholders need.'],
                ] as $b)
                    <div class="flex flex-col bg-white p-7">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $b['icon'] }}" /></svg>
                        </div>
                        <h3 class="mt-5 text-base font-semibold text-slate-900">{{ $b['title'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $b['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Feature row 1: Cross-signal insights ─────────────── --}}
    <section class="bg-slate-50/60 py-20 sm:py-24">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600">Cross-signal insights</p>
                    <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-[2.25rem]">
                        Every signal becomes a task.
                    </h2>
                    <p class="mt-4 text-[15px] leading-7 text-slate-600">
                        Six insight boards — cannibalization, striking distance, content decay, indexing fails, audit vs traffic, and backlink impact — produce ranked action lists, not orphan numbers.
                    </p>
                    <ul class="mt-7 space-y-3 text-[14px] text-slate-700">
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Joins GSC × GA4 × audits × backlinks per page</li>
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Per-country, per-device segmentation built in</li>
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Updated daily with anomaly callouts</li>
                    </ul>
                </div>

                {{-- Mockup: insight cards grid --}}
                <div class="relative">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ([
                                ['label' => 'Cannibalizations', 'value' => '14', 'caption' => '7 high impact', 'tone' => 'amber'],
                                ['label' => 'Striking distance', 'value' => '27', 'caption' => '12 ready to push', 'tone' => 'indigo'],
                                ['label' => 'Content decay', 'value' => '8', 'caption' => '-32% clicks 28d', 'tone' => 'slate'],
                                ['label' => 'Indexing fails', 'value' => '3', 'caption' => '120 lost impr', 'tone' => 'rose'],
                            ] as $c)
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">{{ $c['label'] }}</p>
                                    <p @class([
                                        'mt-1.5 text-2xl font-semibold tabular-nums',
                                        'text-amber-600' => $c['tone'] === 'amber',
                                        'text-indigo-600' => $c['tone'] === 'indigo',
                                        'text-slate-900' => $c['tone'] === 'slate',
                                        'text-rose-600' => $c['tone'] === 'rose',
                                    ])>{{ $c['value'] }}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">{{ $c['caption'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Top striking-distance queries</p>
                            <ul class="mt-3 space-y-1.5 text-[12px]">
                                @foreach ([['best seo tools', '5.2', '12.8k', '1.2%'], ['on-page seo checklist', '7.1', '8.1k', '0.9%'], ['saas seo strategy', '11.4', '5.9k', '0.4%']] as [$q, $pos, $impr, $ctr])
                                    <li class="flex items-center justify-between rounded-md bg-white px-2.5 py-1.5 ring-1 ring-slate-200">
                                        <span class="truncate font-medium text-slate-800">{{ $q }}</span>
                                        <span class="flex shrink-0 items-center gap-3 tabular-nums text-slate-500">
                                            <span>#{{ $pos }}</span>
                                            <span>{{ $impr }}</span>
                                            <span>{{ $ctr }}</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Feature row 2: Rank tracking ─────────────────────── --}}
    <section class="bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                {{-- Mockup: keyword table with sparkline --}}
                <div class="order-last lg:order-first">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Tracked keywords</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-900">United States · Mobile</p>
                            </div>
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">128 active</span>
                        </div>
                        <table class="min-w-full text-[12px]">
                            <thead class="bg-slate-50/60 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Keyword</th>
                                    <th class="px-3 py-2 text-right font-semibold">Pos</th>
                                    <th class="px-3 py-2 text-right font-semibold">Δ</th>
                                    <th class="px-3 py-2 text-right font-semibold">Trend</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ([
                                    ['best seo tools', '2', '+3', 'M0 12 L8 10 L16 11 L24 8 L32 6 L40 4 L48 3', 'emerald'],
                                    ['saas content marketing', '8', '+1', 'M0 8 L8 9 L16 7 L24 7 L32 6 L40 5 L48 4', 'emerald'],
                                    ['seo audit checklist', '14', '-2', 'M0 4 L8 5 L16 7 L24 6 L32 8 L40 9 L48 11', 'rose'],
                                    ['keyword research guide', '6', '0', 'M0 6 L8 6 L16 5 L24 6 L32 7 L40 6 L48 6', 'slate'],
                                    ['featured snippet tips', '4', '+5', 'M0 11 L8 10 L16 9 L24 7 L32 6 L40 5 L48 3', 'emerald'],
                                ] as [$kw, $pos, $delta, $path, $tone])
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-4 py-2.5 font-medium text-slate-800">{{ $kw }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">#{{ $pos }}</td>
                                        <td @class([
                                            'px-3 py-2.5 text-right tabular-nums font-semibold',
                                            'text-emerald-600' => $tone === 'emerald',
                                            'text-rose-600' => $tone === 'rose',
                                            'text-slate-500' => $tone === 'slate',
                                        ])>{{ $delta }}</td>
                                        <td class="px-3 py-2.5">
                                            <svg viewBox="0 0 48 14" class="ml-auto h-4 w-16" aria-hidden="true">
                                                <path d="{{ $path }}" fill="none" stroke="{{ $tone === 'emerald' ? '#059669' : ($tone === 'rose' ? '#e11d48' : '#94a3b8') }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600">Rank tracking</p>
                    <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-[2.25rem]">
                        Rankings — and the clicks they actually earn.
                    </h2>
                    <p class="mt-4 text-[15px] leading-7 text-slate-600">
                        Most trackers show position. EBQ overlays GSC clicks for the exact query, so you instantly see when a rank gain stops producing traffic — and when SERP features are stealing it.
                    </p>
                    <ul class="mt-7 space-y-3 text-[14px] text-slate-700">
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Country, device, language, and city targeting</li>
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Competitor positions captured every check</li>
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>SERP-feature risk flags + PAA capture</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Feature row 3: Page audits ───────────────────────── --}}
    <section class="bg-slate-50/60 py-20 sm:py-24">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600">Page audits</p>
                    <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-[2.25rem]">
                        CWV, on-page, and content — in one pass.
                    </h2>
                    <p class="mt-4 text-[15px] leading-7 text-slate-600">
                        On-demand audits combine mobile + desktop Core Web Vitals with a deep HTML analyzer and keyword-strategy review. Every finding becomes a prioritized recommendation.
                    </p>
                    <ul class="mt-7 space-y-3 text-[14px] text-slate-700">
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Full CWV: LCP, CLS, INP, TBT, FCP, TTFB</li>
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>SEO checks: meta, headings, schema, hreflang, alt</li>
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>One-click resubmit via Google Indexing API</li>
                    </ul>
                </div>

                {{-- Mockup: CWV stat grid + checklist --}}
                <div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">/blog/saas-seo-guide</p>
                            <span class="rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-100">Score 72</span>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2.5">
                            @foreach ([
                                ['LCP', '2.8s', 'amber'],
                                ['CLS', '0.04', 'emerald'],
                                ['INP', '180ms', 'emerald'],
                                ['TBT', '410ms', 'amber'],
                                ['FCP', '1.6s', 'emerald'],
                                ['TTFB', '720ms', 'amber'],
                            ] as [$lbl, $val, $tone])
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $lbl }}</p>
                                    <p @class([
                                        'mt-1 text-base font-semibold tabular-nums',
                                        'text-emerald-600' => $tone === 'emerald',
                                        'text-amber-600' => $tone === 'amber',
                                        'text-rose-600' => $tone === 'rose',
                                    ])>{{ $val }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Top recommendations</p>
                            <ul class="mt-3 space-y-2 text-[12px]">
                                @foreach ([
                                    ['rose', 'Render-blocking CSS — split into critical + async (180KB)'],
                                    ['amber', 'Image alt missing on 7 hero/inline images'],
                                    ['amber', 'Canonical tag missing — set to self'],
                                    ['slate', 'Internal links: 3 orphaned, add 2 from /pricing'],
                                ] as [$tone, $text])
                                    <li class="flex items-start gap-2.5">
                                        <span @class([
                                            'mt-1 h-1.5 w-1.5 flex-none rounded-full',
                                            'bg-rose-500' => $tone === 'rose',
                                            'bg-amber-500' => $tone === 'amber',
                                            'bg-slate-400' => $tone === 'slate',
                                        ])></span>
                                        <span class="text-slate-700">{{ $text }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Feature row 4: Backlink impact ───────────────────── --}}
    <section class="bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                {{-- Mockup: backlink impact table --}}
                <div class="order-last lg:order-first">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Backlink impact · last 28d</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-900">Sorted by Δ clicks</p>
                            </div>
                        </div>
                        <table class="min-w-full text-[12px]">
                            <thead class="bg-slate-50/60 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Target page</th>
                                    <th class="px-3 py-2 text-right font-semibold">Links</th>
                                    <th class="px-3 py-2 text-right font-semibold">DA</th>
                                    <th class="px-3 py-2 text-right font-semibold">Δ clicks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ([
                                    ['/pricing', 3, 58, '+412', 'emerald'],
                                    ['/blog/saas-seo', 7, 49, '+186', 'emerald'],
                                    ['/features', 2, 61, '+94', 'emerald'],
                                    ['/blog/keyword-research', 4, 42, '+38', 'emerald'],
                                    ['/product/ai-writer', 4, 41, '-22', 'rose'],
                                ] as [$p, $n, $da, $delta, $tone])
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-4 py-2.5 font-medium text-slate-800">{{ $p }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-600">{{ $n }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-600">{{ $da }}</td>
                                        <td @class([
                                            'px-3 py-2.5 text-right tabular-nums font-semibold',
                                            'text-emerald-600' => $tone === 'emerald',
                                            'text-rose-600' => $tone === 'rose',
                                        ])>{{ $delta }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-600">Backlink impact</p>
                    <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-[2.25rem]">
                        Prove which links actually moved the needle.
                    </h2>
                    <p class="mt-4 text-[15px] leading-7 text-slate-600">
                        Upload, verify, and measure. EBQ shows you the click delta on every target page in the 28 days after a link goes live — sorted by biggest lift, so outreach proves itself.
                    </p>
                    <ul class="mt-7 space-y-3 text-[14px] text-slate-700">
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Live verification of presence, anchor, rel</li>
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Bulk import or manual entry, deduped</li>
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Filters by DA, spam, dofollow, anchor, date</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Workflow strip ───────────────────────────────────── --}}
    <section class="bg-slate-50/60 py-20 sm:py-24">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Workflow</p>
                <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">A weekly rhythm your team can keep.</h2>
            </div>

            <ol class="mt-14 grid gap-px overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['01', 'Discover', 'Anomalies, content decay, indexing fails surface daily.'],
                    ['02', 'Prioritize', 'Striking-distance and cannibalization scored by impact.'],
                    ['03', 'Execute', 'Ship fixes from audits, briefs, or the WordPress sidebar.'],
                    ['04', 'Measure', 'Reports auto-attach rank, click, and CWV deltas.'],
                ] as [$n, $title, $desc])
                    <li class="relative bg-white p-7">
                        <p class="text-[11px] font-mono font-semibold tracking-wider text-slate-400">{{ $n }}</p>
                        <h3 class="mt-3 text-base font-semibold text-slate-900">{{ $title }}</h3>
                        <p class="mt-2 text-[13px] leading-6 text-slate-600">{{ $desc }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ── Reporting + WordPress pair ───────────────────────── --}}
    <section id="wordpress" class="bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Reporting + WordPress</p>
                <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Insights where stakeholders read them.</h2>
                <p class="mt-4 text-base leading-7 text-slate-600">Auto-sent executive reports for leadership. Editor-side context for content teams. No tab switching.</p>
            </div>

            <div class="mt-14 grid gap-6 lg:grid-cols-2">
                {{-- Report email mockup --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Weekly Growth Report</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">example.com · Apr 13–19</p>
                        </div>
                        <span class="rounded-md bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">+12% w/w</span>
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-2.5">
                        @foreach ([['Users', '8.4k', '+12%'], ['Clicks', '3.1k', '+8%'], ['Avg pos', '14.2', '-0.6']] as [$l, $v, $d])
                            <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3 text-center">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $l }}</p>
                                <p class="mt-1 text-base font-semibold tabular-nums text-slate-900">{{ $v }}</p>
                                <p class="text-[10px] font-semibold text-emerald-600">{{ $d }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/60 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-indigo-700">Action insights</p>
                        <ul class="mt-2 space-y-1.5 text-[12px] text-slate-700">
                            <li>• 5 striking-distance keywords — push title + meta this sprint</li>
                            <li>• 3 pages cannibalizing on "saas seo guide"</li>
                            <li>• 1 indexing fail still pulling 120 impressions/wk</li>
                        </ul>
                    </div>
                </div>

                {{-- WordPress sidebar mockup --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
                        <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        <span class="ml-2 text-[11px] font-medium text-slate-500">Gutenberg · EBQ SEO</span>
                    </div>

                    <div class="mt-4 space-y-3 text-[12px]">
                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Search performance · 30d</p>
                            <div class="mt-2 grid grid-cols-4 gap-1.5">
                                @foreach ([['Clicks', '1,284'], ['Impr', '21.4k'], ['Pos', '6.4'], ['CTR', '6.0%']] as [$l, $v])
                                    <div class="rounded bg-white px-2 py-1.5 text-center ring-1 ring-slate-200">
                                        <span class="block text-[9px] font-medium uppercase text-slate-500">{{ $l }}</span>
                                        <span class="block tabular-nums font-semibold text-slate-900">{{ $v }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Rank tracking</p>
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="rounded-md bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-900 ring-1 ring-slate-200">#4</span>
                                <span class="text-[10px] font-semibold text-emerald-700">▲ 2</span>
                                <span class="text-[10px] text-slate-500">"best seo tools"</span>
                            </div>
                        </div>

                        <div class="rounded-lg border border-amber-100 bg-amber-50/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700">Cannibalization</p>
                            <p class="mt-1 text-[11px] text-slate-700">"best seo tools" splits with <span class="font-medium">/blog/seo-tools-guide</span></p>
                        </div>

                        <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Striking distance</p>
                            <p class="mt-1 text-[11px] text-slate-700">3 queries at pos 5–20 with below-curve CTR</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── FAQ ──────────────────────────────────────────────── --}}
    <section id="faq" class="bg-slate-50/60 py-20 sm:py-24">
        <div class="mx-auto max-w-3xl px-6 lg:px-8">
            <div class="text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">FAQ</p>
                <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Common questions before you switch.</h2>
            </div>

            <div class="mt-12 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
                @foreach ([
                    ['How long does setup take?', 'Most teams connect Search Console + GA4 and run their first audit in under ten minutes.'],
                    ['Do you replace our weekly reporting docs?', 'Yes. EBQ sends scheduled reports with action insights, YoY comparisons, and trend deltas — ready for stakeholders.'],
                    ['Can I invite team members and clients?', 'Yes. Roles are website-scoped with feature-level permissions. Invitees auto-accept on signup.'],
                    ['Do you support WordPress?', 'Yes. The plugin surfaces ranking, click, and content insights directly in Gutenberg and WP admin.'],
                    ['Is there a free plan?', 'Yes. The Free plan covers one website, basic Search Console performance, and 10 audits per month.'],
                ] as [$q, $a])
                    <details class="group p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex cursor-pointer items-center justify-between gap-3 text-[15px] font-semibold text-slate-900">
                            <span>{{ $q }}</span>
                            <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-slate-100 text-slate-600 transition group-open:rotate-45 group-open:bg-slate-900 group-open:text-white">
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            </span>
                        </summary>
                        <p class="mt-3 text-[14px] leading-7 text-slate-600">{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Final CTA ────────────────────────────────────────── --}}
    <section class="bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-4xl px-6 lg:px-8">
            <div class="rounded-3xl border border-slate-200 bg-slate-50/60 px-6 py-14 text-center sm:px-12">
                <h2 class="text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Run SEO like a product team.</h2>
                <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-600">Connect your data, see the next high-impact fix, and ship it before your next stand-up.</p>
                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Start free trial
                    </a>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900">
                        See pricing
                    </a>
                </div>
            </div>
        </div>
    </section>

    @if (\App\Support\Recaptcha::isEnabled())
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
    <script>
        (function () {
            var form = document.getElementById('guest-audit-form');
            if (!form) return;

            var btn = document.getElementById('ga-submit');
            var label = document.getElementById('ga-label');
            var spinner = document.getElementById('ga-spinner');
            var arrow = document.getElementById('ga-arrow');
            var errorBox = document.getElementById('ga-error');
            var csrf = document.querySelector('meta[name="csrf-token"]');

            var signupModal = document.getElementById('ga-signup-modal');
            var emailModal = document.getElementById('ga-email-modal');
            var emailForm = document.getElementById('ga-email-form');
            var nameInput = document.getElementById('ga-name');
            var emailInput = document.getElementById('ga-email');
            var emailModalMsg = document.getElementById('ga-email-modal-msg');
            var emailError = document.getElementById('ga-email-error');
            var emailSubmit = document.getElementById('ga-email-submit');
            var emailLabel = document.getElementById('ga-email-label');
            var emailSpinner = document.getElementById('ga-email-spinner');
            var successCard = document.getElementById('ga-success');
            var successMsg = document.getElementById('ga-success-msg');

            // Lead details captured via the email modal on the 2nd audit.
            var capturedName = '';
            var capturedEmail = '';

            function showError(msg) { errorBox.textContent = msg; errorBox.classList.remove('hidden'); }
            function clearError() { errorBox.textContent = ''; errorBox.classList.add('hidden'); }
            function setLoading(on) {
                btn.disabled = on;
                form.setAttribute('aria-busy', on ? 'true' : 'false');
                label.textContent = on ? 'Auditing your page…' : 'Run free audit';
                spinner.classList.toggle('hidden', !on);
                arrow.classList.toggle('hidden', on);
            }
            function setEmailLoading(on) {
                if (emailSubmit) { emailSubmit.disabled = on; }
                if (emailSpinner) { emailSpinner.classList.toggle('hidden', !on); }
                if (emailLabel) { emailLabel.textContent = on ? 'Sending…' : 'Email me my audit'; }
            }
            function resetCaptcha() {
                if (window.grecaptcha && typeof window.grecaptcha.reset === 'function') {
                    try { window.grecaptcha.reset(); } catch (e) {}
                }
            }
            function captchaToken() {
                // The widget can live in the hero form or the email modal, so query
                // document-wide rather than scoping to the form.
                var c = document.querySelector('textarea[name="g-recaptcha-response"]');
                return c ? c.value : null;
            }

            // The single captcha widget gets relocated between the hero form and the
            // email modal so it's always reachable — never stranded behind the popup.
            var captchaWidget = document.querySelector('.g-recaptcha');
            var heroCaptchaSlot = document.getElementById('ga-captcha-hero-slot');
            var modalCaptchaSlot = document.getElementById('ga-captcha-modal-slot');
            function moveCaptchaTo(slot) {
                if (captchaWidget && slot && captchaWidget.parentNode !== slot) {
                    slot.appendChild(captchaWidget);
                }
            }
            function toggleModal(el, on) {
                if (!el) return;
                el.classList.toggle('hidden', !on);
                el.classList.toggle('flex', on);
            }
            function openEmailModal(msg) {
                if (!emailModal) return;
                if (emailModalMsg && msg) { emailModalMsg.textContent = msg; }
                if (emailError) { emailError.classList.add('hidden'); }
                moveCaptchaTo(modalCaptchaSlot);
                toggleModal(emailModal, true);
                if (nameInput) { nameInput.focus(); }
            }
            function openSignupModal(msg) {
                if (!signupModal) { window.location.href = '{{ route('register') }}'; return; }
                var m = document.getElementById('ga-signup-msg');
                if (m && msg) { m.textContent = msg; }
                toggleModal(signupModal, true);
            }
            function showSuccess(msg) {
                if (successMsg && msg) { successMsg.textContent = msg; }
                form.classList.add('hidden');
                if (successCard) { successCard.classList.remove('hidden'); }
            }

            // Wire modal dismissals.
            if (signupModal) {
                var sClose = document.getElementById('ga-signup-close');
                var sBack = document.getElementById('ga-signup-backdrop');
                if (sClose) { sClose.addEventListener('click', function () { toggleModal(signupModal, false); }); }
                if (sBack) { sBack.addEventListener('click', function () { toggleModal(signupModal, false); }); }
            }
            if (emailModal) {
                var eCancel = document.getElementById('ga-email-cancel');
                var eBack = document.getElementById('ga-email-backdrop');
                var dismiss = function () { toggleModal(emailModal, false); moveCaptchaTo(heroCaptchaSlot); setLoading(false); setEmailLoading(false); };
                if (eCancel) { eCancel.addEventListener('click', dismiss); }
                if (eBack) { eBack.addEventListener('click', dismiss); }
            }

            function runAudit() {
                clearError();
                var url = (document.getElementById('ga-url').value || '').trim();
                var keyword = (document.getElementById('ga-keyword').value || '').trim();
                if (!url) { showError('Please enter your page URL.'); return; }
                if (!keyword) { showError('Please enter a target keyword.'); return; }

                var payload = { url: url, keyword: keyword };
                var countryEl = document.getElementById('ga-country');
                if (countryEl && countryEl.value) { payload.country = countryEl.value; }
                if (capturedEmail) { payload.email = capturedEmail; payload.name = capturedName; }
                var token = captchaToken();
                if (token) { payload['g-recaptcha-response'] = token; }

                setLoading(true);
                if (emailModal && emailModal.classList.contains('flex')) { setEmailLoading(true); }

                fetch(form.getAttribute('data-action'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
                    },
                    body: JSON.stringify(payload)
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        return { status: res.status, data: data };
                    });
                }).then(function (r) {
                    // 2nd audit → emailed only, never shown on screen.
                    if (r.status === 202 && r.data.emailed) {
                        toggleModal(emailModal, false);
                        showSuccess(r.data.message);
                        return;
                    }
                    // 1st audit → show the report.
                    if (r.status === 202 && r.data.results_url) {
                        window.location.href = r.data.results_url;
                        return;
                    }
                    if (r.data && r.data.require === 'email') {
                        setLoading(false); setEmailLoading(false);
                        openEmailModal(r.data.message);
                        return;
                    }
                    if (r.data && r.data.require === 'signup') {
                        setLoading(false); setEmailLoading(false);
                        toggleModal(emailModal, false);
                        openSignupModal(r.data.message);
                        return;
                    }
                    // Errors.
                    var msg = r.data.message;
                    var errs = r.data.errors || {};
                    if (!msg) {
                        var first = Object.keys(errs)[0];
                        if (first && errs[first] && errs[first][0]) { msg = errs[first][0]; }
                    }
                    msg = msg || 'Something went wrong. Please try again.';
                    // Only re-run the captcha if it was the thing that failed.
                    if (errs['g-recaptcha-response']) { resetCaptcha(); }
                    if (emailModal && emailModal.classList.contains('flex')) {
                        if (emailError) { emailError.textContent = msg; emailError.classList.remove('hidden'); }
                    } else {
                        showError(msg);
                    }
                    setLoading(false); setEmailLoading(false);
                }).catch(function () {
                    showError('Network error. Please check your connection and try again.');
                    setLoading(false); setEmailLoading(false);
                });
            }

            form.addEventListener('submit', function (e) { e.preventDefault(); runAudit(); });

            if (emailForm) {
                emailForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (emailError) { emailError.classList.add('hidden'); }
                    var nm = (nameInput.value || '').trim();
                    var em = (emailInput.value || '').trim();
                    if (!nm) { emailError.textContent = 'Please enter your name.'; emailError.classList.remove('hidden'); nameInput.focus(); return; }
                    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)) { emailError.textContent = 'Please enter a valid email address.'; emailError.classList.remove('hidden'); emailInput.focus(); return; }
                    capturedName = nm;
                    capturedEmail = em;
                    runAudit();
                });
            }
        })();
    </script>
</x-marketing.page>
