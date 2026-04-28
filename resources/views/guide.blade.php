<x-marketing.page
    title="Quick Start Guide — EBQ"
    description="Set up EBQ end to end: connect Search Console, Analytics, and Indexing; track keywords and backlinks; run audits; turn on anomaly alerts; schedule reports."
    active="guide"
>
    <article class="bg-white">
        {{-- ── Article hero ──────────────────────────────────────── --}}
        <header class="border-b border-slate-200">
            <div class="mx-auto max-w-3xl px-6 py-16 lg:px-8 lg:py-20">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Quick start guide · 10 min read</p>
                <h1 class="mt-4 text-balance text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
                    From signup to a working SEO loop, in about fifteen minutes.
                </h1>
                <p class="mt-5 text-balance text-[17px] leading-8 text-slate-600">
                    EBQ is built around a weekly rhythm — discover, prioritize, execute, measure. This guide walks you through the first run of that loop on your own data, including the backlinks, alerts, and WordPress integrations most teams turn on in week one.
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
                        ['#step-5', '5', 'Import or track backlinks'],
                        ['#step-6', '6', 'Review the insight boards'],
                        ['#step-7', '7', 'Schedule reports + alerts'],
                        ['#step-8', '8', 'Install the WordPress plugin'],
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
                            'body' => 'From the dashboard, choose <strong>Websites → Add website</strong> and paste your canonical URL. EBQ probes robots.txt, sitemaps, and indexable structure on first save, and pins the timezone EBQ will use for daily syncs and scheduled reports.',
                            'tip' => 'Tip: use the exact protocol and host registered in Search Console. EBQ matches GSC properties by URL prefix.',
                        ],
                        [
                            'id' => 'step-2',
                            'n' => '02',
                            'time' => '~ 2 min',
                            'title' => 'Connect Search Console + GA4',
                            'body' => 'Open <strong>Settings → Integrations</strong> and click <em>Connect Google</em>. EBQ requests <code>analytics.readonly</code>, <code>webmasters.readonly</code>, and <code>indexing</code> in a single consent screen. Refresh tokens are encrypted at rest and rotated automatically; you can revoke at any time from the same screen.',
                            'tip' => 'You can grant only one source if you prefer — EBQ will still operate, with reduced cross-signal coverage. The Indexing scope is what powers one-click resubmit in step 4.',
                        ],
                        [
                            'id' => 'step-3',
                            'n' => '03',
                            'time' => '~ 2 min',
                            'title' => 'Track keywords and competitors',
                            'body' => 'In <strong>Keywords</strong>, paste up to your plan limit. Set country, device, and (optionally) language, city, and up to three competitors. EBQ captures real SERPs on each check, flags features (PAA, AI overview, video, sitelinks), and overlays your GSC clicks for the same query so a rank gain is judged on traffic, not just position.',
                            'tip' => 'Start with 20–40 high-intent terms. You can always add more — empty rows aren\'t penalized. Use on-demand re-checks after a publish to capture rank movement faster than the daily cycle.',
                        ],
                        [
                            'id' => 'step-4',
                            'n' => '04',
                            'time' => '~ 1 min',
                            'title' => 'Run a page audit',
                            'body' => 'Open <strong>Pages → Audits → New audit</strong>. Pick the URL, select mobile or desktop, optionally provide the target keyword. Audits combine Core Web Vitals (LCP, CLS, INP, TBT, FCP, TTFB) + on-page SEO + content review and finish in under 60 seconds. Once you ship a fix, hit <em>Resubmit to Google</em> on the audit to re-request indexing without leaving EBQ.',
                            'tip' => 'Audits with a target keyword unlock the keyword-strategy review and topical-gap analysis. Re-audit after every deploy to anchor before/after evidence.',
                        ],
                        [
                            'id' => 'step-5',
                            'n' => '05',
                            'time' => '~ 2 min',
                            'title' => 'Import or track backlinks',
                            'body' => 'Go to <strong>Backlinks</strong> and paste a list of source URLs (or upload a CSV). EBQ verifies presence, anchor text, and rel attributes on a recurring schedule, then measures the 28-day click delta on each target page after the link goes live so you can prove which links actually moved the needle.',
                            'tip' => 'Filter by DA, dofollow, anchor, or date to triage. Pro+ plans unlock competitor backlink prospecting from inside the same workspace.',
                        ],
                        [
                            'id' => 'step-6',
                            'n' => '06',
                            'time' => '~ 2 min',
                            'title' => 'Review the insight boards',
                            'body' => 'Visit <strong>Dashboard → Insights</strong>. The six boards — cannibalization, striking distance, content decay, indexing fails with traffic, audit-vs-traffic, and backlink impact — populate within a few minutes of the first GSC sync and refresh daily. Each row links straight to the offending page so the next action is one click away.',
                            'tip' => 'Most teams ship 1–3 wins from striking-distance in the first week. Cannibalization is usually the highest-leverage second pass.',
                        ],
                        [
                            'id' => 'step-7',
                            'n' => '07',
                            'time' => '~ 2 min',
                            'title' => 'Schedule reports + turn on alerts',
                            'body' => 'In <strong>Reports → Schedules</strong> add recipients, pick cadence (daily, weekly, or monthly), and a delivery time. Each report includes YoY, top gainers/losers, traffic-source concentration, and the top-5 action insights. Anomaly alerts ride along to the same recipient list — EBQ compares yesterday against a 28-day baseline on clicks, sessions, and avg position, gated by relative drop and z-score so the inbox stays quiet.',
                            'tip' => 'Add a stakeholder address and yourself — even unread reports build a reviewable history. Agency plans unlock white-label PDF export.',
                        ],
                        [
                            'id' => 'step-8',
                            'n' => '08',
                            'time' => '~ 3 min',
                            'title' => 'Install the WordPress plugin',
                            'body' => 'If you publish on WordPress, install the EBQ plugin from <strong>Settings → WordPress</strong> and use one-click Connect to bind it to this website. Editors then see live rank, 30-day clicks, cannibalization, and striking-distance opportunities inside the Gutenberg sidebar, the posts list, and the WP dashboard widget — without ever leaving the editor.',
                            'tip' => 'Tokens are issued per website and stored server-side — they never live in browser JS. Revoke from <strong>Settings → WordPress</strong> at any time.',
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
                            'Re-run an audit on every page you ship a fix to, then resubmit to Google from the audit view.',
                            'Skim new backlinks landed this week — verify presence and check the 28-day click delta on the target page.',
                            'Triage anomaly alerts within 24 hours; confirm whether the drop is real, seasonal, or a tracking issue.',
                            'Read your scheduled report — note YoY direction and which actions from last week actually moved the needle.',
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
