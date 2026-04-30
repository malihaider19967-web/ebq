<x-marketing.page
    title="Guide — EBQ"
    description="The complete EBQ user guide: connect Search Console, Analytics, and the Indexing API; track keywords and backlinks; run page audits; turn on alerts; schedule reports; install the WordPress plugin."
    active="guide"
>
    @php
        $guideVisuals = [
            ['anchor' => 'dashboard', 'title' => 'Dashboard overview', 'file' => 'images/guide/dashboard-overview.png', 'alt' => 'EBQ dashboard overview cards and charts'],
            ['anchor' => 'insight-cards', 'title' => 'Action insights', 'file' => 'images/guide/action-insights.png', 'alt' => 'Dashboard action insights cards in EBQ'],
            ['anchor' => 'keywords', 'title' => 'Keywords', 'file' => 'images/guide/keywords-workspace.png', 'alt' => 'Keywords workspace table in EBQ'],
            ['anchor' => 'pages', 'title' => 'Pages', 'file' => 'images/guide/pages-workspace.png', 'alt' => 'Pages workspace performance table in EBQ'],
            ['anchor' => 'rank-tracking', 'title' => 'Rank tracking', 'file' => 'images/guide/rank-tracking.png', 'alt' => 'Rank tracking trend and keyword positions in EBQ'],
            ['anchor' => 'custom-audit', 'title' => 'Custom audit', 'file' => 'images/guide/custom-audit.png', 'alt' => 'Custom page audit form in EBQ'],
            ['anchor' => 'audit-report-sections', 'title' => 'Audit report', 'file' => 'images/guide/audit-report-sections.png', 'alt' => 'Page audit detail report sections in EBQ'],
            ['anchor' => 'insights-panel', 'title' => 'Reports insights', 'file' => 'images/guide/reports-insights.png', 'alt' => 'Reports insights tab in EBQ'],
            ['anchor' => 'growth-reports', 'title' => 'Growth reports', 'file' => 'images/guide/growth-reports.png', 'alt' => 'Custom growth report builder tab in EBQ'],
        ];
    @endphp

    {{-- ── Hero ──────────────────────────────────────────────── --}}
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-3xl px-6 py-16 lg:px-8 lg:py-20">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">User guide · 15 min read</p>
            <h1 class="mt-4 text-balance text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
                The complete EBQ guide.
            </h1>
            <p class="mt-5 text-balance text-[17px] leading-8 text-slate-600">
                Everything a new team needs to set up EBQ end to end and run a productive weekly SEO loop. Each step has the screen you'll see, what to click, what to expect, and the common mistakes to avoid.
            </p>
            <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row">
                <a href="#step-1" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Start with step 1</a>
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900">Create a free account</a>
            </div>
        </div>
    </section>

    <section class="border-b border-slate-200 bg-slate-50/50">
        <div class="mx-auto max-w-6xl px-6 py-10 lg:px-8">
            <h2 class="text-xl font-semibold tracking-tight text-slate-900">Section visual map</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Each dashboard info icon opens one of these sections. Place real screenshots at the listed paths to upgrade placeholders automatically.
            </p>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($guideVisuals as $visual)
                    <a href="#{{ $visual['anchor'] }}" class="rounded-xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm">
                        @if (file_exists(public_path($visual['file'])))
                            <figure>
                                <img src="{{ asset($visual['file']) }}" alt="{{ $visual['alt'] }}" class="h-36 w-full rounded-lg border border-slate-200 object-cover">
                                <figcaption class="mt-1 text-[11px] text-slate-500">{{ $visual['alt'] }}</figcaption>
                            </figure>
                        @else
                            <div class="flex h-36 w-full items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-center">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Guide visual</p>
                                    <p class="mt-1 font-mono text-[10px] text-slate-400">{{ $visual['file'] }}</p>
                                </div>
                            </div>
                        @endif
                        <p class="mt-3 text-sm font-semibold text-slate-900">{{ $visual['title'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">Jump to section</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Two-column body: TOC + content ───────────────────── --}}
    <section class="bg-white">
        <div class="mx-auto grid max-w-6xl gap-12 px-6 py-16 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-16 lg:px-8 lg:py-20">

            {{-- Sticky TOC --}}
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">In this guide</p>
                <nav aria-label="Guide sections" class="mt-3 flex flex-col gap-1 text-sm">
                    @php
                        $tocWorkspace = [
                            ['#dashboard', 'Dashboard overview'],
                            ['#insight-cards', 'Action insights cards'],
                            ['#keywords', 'Keywords workspace'],
                            ['#pages', 'Pages workspace'],
                            ['#rank-tracking', 'Rank tracking'],
                            ['#custom-audit', 'Custom page audit'],
                            ['#audit-report-sections', 'Page audit report sections'],
                            ['#insights-panel', 'Reports insights panel'],
                            ['#growth-reports', 'Custom growth reports'],
                        ];
                        $tocSetup = [
                            ['#step-1', '01', 'Add your first website'],
                            ['#step-2', '02', 'Connect Search Console + GA4'],
                            ['#step-3', '03', 'Track keywords + competitors'],
                            ['#step-4', '04', 'Run a page audit'],
                            ['#step-5', '05', 'Import or track backlinks'],
                            ['#step-6', '06', 'Review the insight boards'],
                            ['#step-7', '07', 'Schedule reports + alerts'],
                            ['#step-8', '08', 'Install the WordPress plugin'],
                        ];
                        $tocReference = [
                            ['#metric-glossary', 'Metric glossary'],
                            ['#troubleshooting', 'Troubleshooting'],
                            ['#faq', 'FAQ'],
                            ['#weekly-rhythm', 'A weekly rhythm'],
                        ];
                    @endphp
                    @foreach ($tocWorkspace as [$href, $label])
                        <a href="{{ $href }}" class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                            <span class="font-mono text-[11px] text-slate-400">→</span>
                            <span>{{ $label }}</span>
                        </a>
                    @endforeach
                    <div class="my-3 h-px bg-slate-200"></div>
                    @foreach ($tocSetup as [$href, $n, $label])
                        <a href="{{ $href }}" class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                            <span class="font-mono text-[11px] text-slate-400">{{ $n }}</span>
                            <span>{{ $label }}</span>
                        </a>
                    @endforeach
                    <div class="my-3 h-px bg-slate-200"></div>
                    @foreach ($tocReference as [$href, $label])
                        <a href="{{ $href }}" class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                            <span class="font-mono text-[11px] text-slate-400">·</span>
                            <span>{{ $label }}</span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            {{-- Main content --}}
            <div class="prose prose-slate max-w-none prose-headings:tracking-tight prose-h2:text-3xl prose-h2:font-semibold prose-h2:mt-0 prose-h3:text-lg prose-h3:font-semibold">

                {{-- ── STEP 1 ─────────────────────────────────── --}}
                <span id="websites" class="block scroll-mt-24"></span>
                <section id="step-1" class="not-prose scroll-mt-24">
                    <div class="flex items-baseline gap-4">
                        <span class="font-mono text-sm font-semibold text-slate-400">01</span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-600">~ 1 min</span>
                    </div>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Add your first website</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">
                        Every signal in EBQ — clicks, ranks, audits, backlinks, alerts — is scoped to a website. Before anything else syncs, EBQ needs to know which property it should hold.
                    </p>

                    {{-- Mockup: add website form --}}
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Websites · Add website</p>
                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">New</span>
                        </div>
                        <div class="mt-4 space-y-3 text-[12px]">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Canonical URL</p>
                                <div class="mt-1.5 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 font-mono text-slate-700">https://example.com</div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Default country</p>
                                    <div class="mt-1.5 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 text-slate-700">United States</div>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Timezone</p>
                                    <div class="mt-1.5 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 text-slate-700">America/New_York</div>
                                </div>
                            </div>
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 text-[12px] text-emerald-800">
                                <span class="font-semibold">robots.txt found</span> · sitemap.xml: 4 indices, 1,284 URLs · canonical scheme matches
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">What you'll see</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">A live probe of robots.txt, sitemaps, and canonical configuration appears below the form.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">What to do</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">Use the exact protocol and host registered in Search Console — trailing slashes and <code>www</code> matter for property matching.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Pitfall</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">Mismatched protocol (http vs https) or host (with vs without www) is the #1 reason GSC sync returns zero rows.</p>
                        </div>
                    </div>
                </section>

                {{-- ── STEP 2 ─────────────────────────────────── --}}
                <span id="integrations" class="block scroll-mt-24"></span>
                <span id="settings" class="block scroll-mt-24"></span>
                <section id="step-2" class="not-prose mt-20 scroll-mt-24">
                    <div class="flex items-baseline gap-4">
                        <span class="font-mono text-sm font-semibold text-slate-400">02</span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-600">~ 2 min</span>
                    </div>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Connect Search Console + GA4</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">
                        EBQ uses three Google scopes, all granted in a single OAuth consent screen. Refresh tokens are encrypted at rest and rotated automatically — and you can revoke any time from the same screen.
                    </p>

                    {{-- Mockup: OAuth consent --}}
                    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-3">
                            <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                            <span class="ml-2 text-[11px] font-medium text-slate-500">accounts.google.com — EBQ wants access to your Google Account</span>
                        </div>
                        <div class="px-5 py-5">
                            <p class="text-[13px] text-slate-700">EBQ would like to:</p>
                            <ul class="mt-3 space-y-2.5 text-[12px]">
                                @foreach ([
                                    ['See and download your Google Analytics data', 'analytics.readonly', 'sensitive'],
                                    ['View Search Console data for your verified sites', 'webmasters.readonly', 'standard'],
                                    ['Submit data to Google for indexing', 'indexing', 'standard'],
                                ] as [$desc, $scope, $tone])
                                    <li class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2.5">
                                        <span @class([
                                            'mt-0.5 h-4 w-4 flex-none rounded',
                                            'bg-amber-200' => $tone === 'sensitive',
                                            'bg-slate-200' => $tone === 'standard',
                                        ])></span>
                                        <div>
                                            <p class="font-medium text-slate-800">{{ $desc }}</p>
                                            <p class="font-mono text-[10.5px] text-slate-500">{{ $scope }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-4 flex items-center gap-2">
                                <span class="rounded-md bg-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-700">Cancel</span>
                                <span class="rounded-md bg-indigo-600 px-3 py-1.5 text-[11px] font-semibold text-white">Allow</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Why three scopes</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700"><strong>analytics.readonly</strong> for sessions/users, <strong>webmasters.readonly</strong> for clicks &amp; positions, <strong>indexing</strong> for one-click resubmit from page audits.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Token safety</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">Tokens never leave the server. Refresh tokens are encrypted with the app key; access tokens are short-lived and rotated automatically.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Partial grants</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">If you uncheck a scope, EBQ keeps working with reduced coverage. You can re-grant later from the same screen.</p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50/60 px-5 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-700">Heads up</p>
                        <p class="mt-1 text-[14px] leading-6 text-slate-700">Use the same Google account that owns the GSC property and the GA4 view you want to track. EBQ matches by URL prefix in GSC and by property ID in GA4 — if those don't appear in the dropdown after consent, you're signed in with the wrong account.</p>
                    </div>
                </section>

                {{-- ── STEP 3 ─────────────────────────────────── --}}
                <span id="keywords" class="block scroll-mt-24"></span>
                <span id="rank-tracking" class="block scroll-mt-24"></span>
                <section id="step-3" class="not-prose mt-20 scroll-mt-24">
                    <div class="flex items-baseline gap-4">
                        <span class="font-mono text-sm font-semibold text-slate-400">03</span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-600">~ 2 min</span>
                    </div>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Track keywords and competitors</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">
                        Real SERPs captured per device, country, language, and (optional) city. EBQ overlays your GSC clicks for the same query so a rank gain is judged on traffic, not just position.
                    </p>

                    {{-- Mockup: keyword grid --}}
                    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-3">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Keywords · example.com</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900">Targeting · United States · Mobile</p>
                        </div>
                        <table class="min-w-full text-[12px]">
                            <thead class="bg-slate-50/60 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Query</th>
                                    <th class="px-3 py-2 text-right font-semibold">Pos</th>
                                    <th class="px-3 py-2 text-right font-semibold">Δ 7d</th>
                                    <th class="px-3 py-2 text-right font-semibold">Clicks 30d</th>
                                    <th class="px-3 py-2 text-left font-semibold">SERP features</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ([
                                    ['best seo tools', '#2', '+2', '1,284', ['PAA', 'Sitelinks'], 'emerald'],
                                    ['saas seo guide', '#7', '-1', '218', ['PAA', 'Video'], 'amber'],
                                    ['keyword research tool', '#11', '+4', '94', ['AI overview'], 'emerald'],
                                    ['rank tracker', '#19', '0', '12', ['PAA'], 'slate'],
                                ] as [$q, $pos, $delta, $clicks, $features, $tone])
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-4 py-2.5 font-medium text-slate-800">{{ $q }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-900">{{ $pos }}</td>
                                        <td @class([
                                            'px-3 py-2.5 text-right tabular-nums font-semibold',
                                            'text-emerald-600' => $tone === 'emerald',
                                            'text-amber-600' => $tone === 'amber',
                                            'text-slate-500' => $tone === 'slate',
                                        ])>{{ $delta }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $clicks }}</td>
                                        <td class="px-3 py-2.5">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($features as $f)
                                                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">{{ $f }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h3 class="mt-8 text-lg font-semibold text-slate-900">How to add keywords</h3>
                    <ol class="mt-3 space-y-2 text-[14px] leading-7 text-slate-700">
                        <li><span class="font-mono text-slate-400">1.</span> Open <strong>Keywords</strong>. Click <em>Add keywords</em>.</li>
                        <li><span class="font-mono text-slate-400">2.</span> Paste one per line, or upload a CSV with columns <code>query, country, device, language</code>.</li>
                        <li><span class="font-mono text-slate-400">3.</span> Set the default targeting. Override per-row if you operate in multiple regions.</li>
                        <li><span class="font-mono text-slate-400">4.</span> (Optional) Add up to three competitor domains. EBQ will record their position on every check.</li>
                        <li><span class="font-mono text-slate-400">5.</span> First SERP capture starts within minutes. Subsequent captures run on your plan's interval.</li>
                    </ol>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Start with intent</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">20–40 high-intent terms beat 500 head terms. You can add more later — empty rows aren't penalized.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Re-check after publish</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">After shipping a content update, hit <em>Re-check now</em> to capture rank movement faster than the daily cycle.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">SERP features matter</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">A #2 below an AI overview earns less than a #4 with sitelinks. EBQ flags the feature so the rank number isn't read in isolation.</p>
                        </div>
                    </div>
                </section>

                {{-- ── STEP 4 ─────────────────────────────────── --}}
                <span id="audits" class="block scroll-mt-24"></span>
                <span id="pages" class="block scroll-mt-24"></span>
                <span id="custom-audit" class="block scroll-mt-24"></span>
                <section id="step-4" class="not-prose mt-20 scroll-mt-24">
                    <div class="flex items-baseline gap-4">
                        <span class="font-mono text-sm font-semibold text-slate-400">04</span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-600">~ 1 min</span>
                    </div>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Run a page audit</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">
                        Audits combine Core Web Vitals, on-page SEO, and content review in a single pass and finish in under 60 seconds. After you ship a fix, resubmit the URL to Google's Indexing API without leaving the audit view.
                    </p>

                    {{-- Mockup: audit scorecard --}}
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Audit · /blog/saas-seo-guide</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-900">Mobile · Score 72 · Target: "saas seo guide"</p>
                            </div>
                            <span class="rounded-md bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-100">Needs work</span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2.5">
                            @foreach ([
                                ['LCP', '2.8s', 'amber'],
                                ['CLS', '0.04', 'emerald'],
                                ['INP', '180ms', 'emerald'],
                                ['TBT', '410ms', 'amber'],
                                ['FCP', '1.6s', 'emerald'],
                                ['TTFB', '720ms', 'amber'],
                            ] as [$l, $v, $tone])
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $l }}</p>
                                    <p @class([
                                        'mt-1 text-base font-semibold tabular-nums',
                                        'text-emerald-600' => $tone === 'emerald',
                                        'text-amber-600' => $tone === 'amber',
                                        'text-rose-600' => $tone === 'rose',
                                    ])>{{ $v }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Top recommendations</p>
                            <ul class="mt-3 space-y-2 text-[12px]">
                                @foreach ([
                                    ['rose', 'Render-blocking CSS — split into critical + async (180KB)'],
                                    ['amber', 'Image alt missing on 7 images'],
                                    ['amber', 'Canonical tag missing'],
                                    ['slate', 'Add 2 internal links from /pricing'],
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
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="rounded-md bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white">Resubmit to Google</span>
                            <span class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-700">Re-audit</span>
                            <span class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-700">Download PDF</span>
                        </div>
                    </div>

                    <h3 class="mt-8 text-lg font-semibold text-slate-900">Reading the score</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700">90–100 · Good</p>
                            <p class="mt-1.5 text-[13px] leading-6 text-slate-700">Ship-ready. Re-audit after the next deploy to detect regressions.</p>
                        </div>
                        <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">60–89 · Needs work</p>
                            <p class="mt-1.5 text-[13px] leading-6 text-slate-700">Pick the top two recommendations from the prioritized list.</p>
                        </div>
                        <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-rose-700">0–59 · Poor</p>
                            <p class="mt-1.5 text-[13px] leading-6 text-slate-700">Treat as a sprint goal. Often a CWV regression or schema break.</p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50/60 px-5 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Pro tip</p>
                        <p class="mt-1 text-[14px] leading-6 text-slate-700">Always provide a <em>target keyword</em>. Without it the keyword-strategy review and topical-gap analysis are skipped, and you lose half the value of an audit.</p>
                    </div>

                    <span id="audit-report-sections" class="block scroll-mt-24"></span>
                    <h3 class="mt-8 text-lg font-semibold text-slate-900">Audit report sections explained</h3>
                    <p class="mt-2 text-[14px] leading-7 text-slate-700">
                        This is the exact structure used in the page audit detail screen so the "Guide to this report" icon maps one-to-one with what you are reading.
                    </p>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <figure class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Visual · Page audit report layout</p>
                            </div>
                            <p class="mt-2 text-[12px] text-slate-600">Use this slot for a full-page screenshot from `Page audit` so users can visually match each panel listed below.</p>
                        </figure>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ([
                                ['Summary score', 'Top-level score, benchmark keyword, and market context for this run.'],
                                ['Core Web Vitals', 'LCP, CLS, INP, and supporting speed diagnostics with thresholds.'],
                                ['On-page checks', 'Title, meta, headings, canonical, schema, image alt, and internal links.'],
                                ['SERP snapshot', 'Current organic competitors and readability averages for the keyword and country.'],
                                ['Prioritized fixes', 'Action list ordered by impact and implementation effort.'],
                                ['Re-audit + resubmit', 'Run a fresh audit after deploying and submit URL to indexing from the same flow.'],
                            ] as [$title, $text])
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <p class="text-[12px] font-semibold text-slate-900">{{ $title }}</p>
                                    <p class="mt-1 text-[12px] leading-6 text-slate-700">{{ $text }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ── STEP 5 ─────────────────────────────────── --}}
                <span id="backlinks" class="block scroll-mt-24"></span>
                <section id="step-5" class="not-prose mt-20 scroll-mt-24">
                    <div class="flex items-baseline gap-4">
                        <span class="font-mono text-sm font-semibold text-slate-400">05</span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-600">~ 2 min</span>
                    </div>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Import or track backlinks</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">
                        EBQ verifies presence, anchor, and rel on every check, then measures the 28-day click delta on the target page so you can prove which links actually lifted traffic.
                    </p>

                    {{-- Mockup: backlinks table --}}
                    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-3">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Backlinks · example.com</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900">Verified · 28-day click delta</p>
                        </div>
                        <table class="min-w-full text-[12px]">
                            <thead class="bg-slate-50/60 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Source → target</th>
                                    <th class="px-3 py-2 text-left font-semibold">Anchor</th>
                                    <th class="px-3 py-2 text-right font-semibold">Rel</th>
                                    <th class="px-3 py-2 text-right font-semibold">DA</th>
                                    <th class="px-3 py-2 text-right font-semibold">Δ clicks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ([
                                    ['blog.partner.io → /pricing', 'best pricing for SEO', 'follow', 58, '+412', 'emerald'],
                                    ['news.example.org → /blog/saas-seo', 'SaaS SEO playbook', 'follow', 49, '+186', 'emerald'],
                                    ['forum.community.dev → /features', 'EBQ', 'ugc', 42, '+38', 'emerald'],
                                    ['low-quality.tld → /product/ai-writer', 'click here', 'nofollow', 14, '-22', 'rose'],
                                ] as [$row, $anchor, $rel, $da, $delta, $tone])
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-4 py-2.5 font-medium text-slate-800">{{ $row }}</td>
                                        <td class="px-3 py-2.5 text-slate-600">{{ $anchor }}</td>
                                        <td class="px-3 py-2.5 text-right text-slate-600">{{ $rel }}</td>
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

                    <h3 class="mt-8 text-lg font-semibold text-slate-900">Two ways to add</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">CSV upload</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">Columns: <code>source_url, target_url, anchor</code>. Anchor is optional — EBQ extracts it on first verify.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Manual entry</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">Paste up to 50 source URLs at once. Useful for tracking outreach campaigns as they land.</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Status icons</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700"><span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span> live · <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span> anchor changed · <span class="inline-block h-2 w-2 rounded-full bg-rose-500"></span> removed</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Click delta</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">Compares 28 days <em>after</em> first-seen against 28 days <em>before</em>, on the target page only.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Pro+ only</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">Competitor backlink prospecting surfaces sources linking to rivals but not to you.</p>
                        </div>
                    </div>
                </section>

                {{-- ── STEP 6 ─────────────────────────────────── --}}
                <span id="insights" class="block scroll-mt-24"></span>
                <span id="dashboard" class="block scroll-mt-24"></span>
                <section id="step-6" class="not-prose mt-20 scroll-mt-24">
                    <div class="flex items-baseline gap-4">
                        <span class="font-mono text-sm font-semibold text-slate-400">06</span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-600">~ 2 min</span>
                    </div>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Review the insight boards</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">
                        Six pre-built reports turn raw GSC × GA4 × audit × backlink data into a ranked action list. Each row links straight to the offending page so the next move is one click away.
                    </p>

                    {{-- Mockup: 6 boards --}}
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ([
                            ['Cannibalizations', '14', 'Two pages competing for one query', 'amber'],
                            ['Striking distance', '27', 'Keywords at pos 5–20 with below-curve CTR', 'indigo'],
                            ['Content decay', '8', '90-day click decline beyond seasonality', 'slate'],
                            ['Indexing fails', '3', 'URLs earning impressions but not indexed', 'rose'],
                            ['Audit vs traffic', '11', 'High-traffic pages with poor audit scores', 'slate'],
                            ['Backlink impact', '9', 'Links with measurable lift on the target', 'emerald'],
                        ] as [$lbl, $val, $help, $tone])
                            <div class="rounded-xl border border-slate-200 bg-white p-4">
                                <div class="flex items-baseline justify-between">
                                    <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">{{ $lbl }}</p>
                                    <p @class([
                                        'text-2xl font-semibold tabular-nums',
                                        'text-amber-600' => $tone === 'amber',
                                        'text-indigo-600' => $tone === 'indigo',
                                        'text-slate-900' => $tone === 'slate',
                                        'text-rose-600' => $tone === 'rose',
                                        'text-emerald-600' => $tone === 'emerald',
                                    ])>{{ $val }}</p>
                                </div>
                                <p class="mt-2 text-[12px] leading-5 text-slate-600">{{ $help }}</p>
                            </div>
                        @endforeach
                    </div>

                    <h3 class="mt-8 text-lg font-semibold text-slate-900">How to triage</h3>
                    <ol class="mt-3 space-y-2 text-[14px] leading-7 text-slate-700">
                        <li><span class="font-mono text-slate-400">1.</span> Open <strong>Striking distance</strong> first — these are the fastest wins (small content tweak, internal link, FAQ addition).</li>
                        <li><span class="font-mono text-slate-400">2.</span> Then <strong>Cannibalization</strong> — usually a merge/redirect or canonical fix.</li>
                        <li><span class="font-mono text-slate-400">3.</span> <strong>Indexing fails</strong> for pages that already earn impressions: validate canonical, then resubmit.</li>
                        <li><span class="font-mono text-slate-400">4.</span> <strong>Content decay</strong> last — these are bigger rewrites and need a sprint allocation.</li>
                    </ol>

                    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50/60 px-5 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Tip</p>
                        <p class="mt-1 text-[14px] leading-6 text-slate-700">Most teams ship 1–3 wins from striking-distance in the first week. If you see fewer than 5 candidates total, your keyword set is too narrow — go back to step 3 and broaden.</p>
                    </div>

                    <span id="insight-cards" class="block scroll-mt-24"></span>
                    <h3 class="mt-8 text-lg font-semibold text-slate-900">Action insights cards (dashboard)</h3>
                    <p class="mt-2 text-[14px] leading-7 text-slate-700">
                        The dashboard "Action insights" row is your daily triage queue. Prioritize cards by fastest measurable impact, not by severity color alone.
                    </p>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <figure class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Visual · Action insights cards</p>
                            </div>
                            <p class="mt-2 text-[12px] text-slate-600">Add a screenshot of the dashboard insights card row with labels visible.</p>
                        </figure>
                        <ul class="mt-4 space-y-2 text-[13px] leading-6 text-slate-700">
                            <li><strong>Striking distance:</strong> execute first. Small edits can move queries from positions 5-20 into top 3.</li>
                            <li><strong>Cannibalization:</strong> merge overlapping pages or set canonical to consolidate signals.</li>
                            <li><strong>Indexing issues:</strong> validate canonical and noindex rules, then resubmit.</li>
                            <li><strong>Audit vs traffic:</strong> fix technical blockers on pages already receiving demand.</li>
                        </ul>
                    </div>
                </section>

                {{-- ── STEP 7 ─────────────────────────────────── --}}
                <span id="reports" class="block scroll-mt-24"></span>
                <span id="alerts" class="block scroll-mt-24"></span>
                <section id="step-7" class="not-prose mt-20 scroll-mt-24">
                    <div class="flex items-baseline gap-4">
                        <span class="font-mono text-sm font-semibold text-slate-400">07</span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-600">~ 2 min</span>
                    </div>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Schedule reports + turn on alerts</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">
                        Reports and alerts share one recipient list per website. Reports run on a fixed cadence; alerts fire only when EBQ detects a real anomaly — gated by both relative drop and z-score.
                    </p>

                    <div class="mt-6 grid gap-5 lg:grid-cols-2">
                        {{-- Mockup: schedule form --}}
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Reports · New schedule</p>
                            <div class="mt-3 space-y-3 text-[12px]">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Recipients</p>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-slate-700">you@example.com</span>
                                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-slate-700">cmo@example.com</span>
                                        <span class="rounded-md border border-dashed border-slate-300 px-2 py-0.5 text-slate-500">+ add</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Cadence</p>
                                        <div class="mt-1.5 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 text-slate-700">Weekly · Monday</div>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Time (website TZ)</p>
                                        <div class="mt-1.5 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 text-slate-700">09:00</div>
                                    </div>
                                </div>
                                <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-emerald-800">Anomaly alerts · ON for the same recipients</div>
                            </div>
                        </div>

                        {{-- Mockup: alert email --}}
                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-200 px-5 py-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Alert · example.com</p>
                                    <span class="rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-rose-100">Anomaly</span>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-slate-900">Search clicks dropped 74.9%</p>
                            </div>
                            <div class="px-5 py-5 text-[12px] text-slate-700">
                                <p>An unusual drop was detected on 2026-04-20.</p>
                                <ul class="mt-3 space-y-1.5">
                                    <li class="flex items-center justify-between rounded-md bg-slate-50/60 px-3 py-1.5"><span>Search clicks</span><span class="font-mono text-rose-600">212 vs 844 (z=-3.2)</span></li>
                                    <li class="flex items-center justify-between rounded-md bg-slate-50/60 px-3 py-1.5"><span>Sessions</span><span class="font-mono text-rose-600">480 vs 1,610 (z=-2.8)</span></li>
                                    <li class="flex items-center justify-between rounded-md bg-slate-50/60 px-3 py-1.5"><span>Avg position</span><span class="font-mono text-amber-600">14.2 vs 11.6 (z=-1.9)</span></li>
                                </ul>
                                <div class="mt-4 inline-flex rounded-md bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white">Open EBQ →</div>
                            </div>
                        </div>
                    </div>

                    <h3 class="mt-8 text-lg font-semibold text-slate-900">When alerts fire</h3>
                    <p class="mt-3 text-[14px] leading-7 text-slate-700">EBQ compares yesterday's value against a rolling 28-day baseline and only sends an alert if both gates trip:</p>
                    <ul class="mt-3 space-y-2 text-[14px]">
                        <li class="rounded-xl border border-slate-200 bg-white px-4 py-3"><strong>Relative drop</strong> — the value is at least 30% below the baseline mean.</li>
                        <li class="rounded-xl border border-slate-200 bg-white px-4 py-3"><strong>Z-score</strong> — the deviation is at least 2 standard deviations from the baseline.</li>
                        <li class="rounded-xl border border-slate-200 bg-white px-4 py-3"><strong>24-hour deduplication</strong> — one alert per metric per anomaly, never a flood.</li>
                    </ul>

                    <span id="insights-panel" class="block scroll-mt-24"></span>
                    <h3 class="mt-8 text-lg font-semibold text-slate-900">Reports insights panel (action list view)</h3>
                    <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <figure class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Visual · Reports insights tab</p>
                            </div>
                            <p class="mt-2 text-[12px] text-slate-600">Capture the `Reports` page with the `Insights` tab selected and at least one actionable row.</p>
                        </figure>
                        <p class="mt-4 text-[13px] leading-6 text-slate-700">
                            Use this panel for prioritization meetings. It is designed for "what to ship this week" and should map directly to tickets or sprint tasks.
                        </p>
                    </div>

                    <span id="growth-reports" class="block scroll-mt-24"></span>
                    <h3 class="mt-8 text-lg font-semibold text-slate-900">Custom growth reports (email tab)</h3>
                    <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <figure class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Visual · Custom report builder</p>
                            </div>
                            <p class="mt-2 text-[12px] text-slate-600">Capture the `Custom report` tab showing recipient list, schedule, and content blocks.</p>
                        </figure>
                        <ol class="mt-4 space-y-2 text-[13px] leading-6 text-slate-700">
                            <li><span class="font-mono text-slate-400">1.</span> Select audience and cadence based on decision-making frequency.</li>
                            <li><span class="font-mono text-slate-400">2.</span> Include only sections teams act on; remove noisy vanity blocks.</li>
                            <li><span class="font-mono text-slate-400">3.</span> Align schedule timezone with stakeholder working hours.</li>
                        </ol>
                    </div>
                </section>

                {{-- ── STEP 8 ─────────────────────────────────── --}}
                <span id="wordpress" class="block scroll-mt-24"></span>
                <span id="wordpress-plugin" class="block scroll-mt-24"></span>
                <section id="step-8" class="not-prose mt-20 scroll-mt-24">
                    <div class="flex items-baseline gap-4">
                        <span class="font-mono text-sm font-semibold text-slate-400">08</span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-600">~ 3 min</span>
                    </div>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Install the WordPress plugin</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">
                        If you publish on WordPress, the plugin embeds rank, click, and content opportunities inside Gutenberg, the post list, and the dashboard widget — so editors see EBQ context without ever leaving WordPress. Below is everything the plugin includes, what each feature does, and how to install and operate it safely.
                    </p>

                    {{-- ── 8.1 Gutenberg sidebar ─────────────────────── --}}
                    <h3 class="mt-10 text-lg font-semibold text-slate-900">8.1 Gutenberg sidebar — live SEO context while you write</h3>
                    <p class="mt-2 text-[14px] leading-7 text-slate-600">A docked panel that appears next to every post and page editor. Editors see the same numbers content marketers see in EBQ, without context-switching.</p>

                    <div class="mt-5 grid gap-5 lg:grid-cols-2">
                        {{-- Mockup: WP sidebar (extended) --}}
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
                                <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                                <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                                <span class="ml-2 text-[11px] font-medium text-slate-500">Gutenberg · EBQ SEO</span>
                            </div>
                            <div class="mt-4 space-y-3 text-[12px]">
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Target keyword</p>
                                    <div class="mt-1.5 rounded bg-white px-2 py-1.5 font-mono text-[11px] text-slate-700 ring-1 ring-slate-200">saas seo guide</div>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Search performance · 30d</p>
                                    <div class="mt-2 grid grid-cols-4 gap-1.5">
                                        @foreach ([['Clicks', '1,284'], ['Impr', '21.4k'], ['Pos', '6.4'], ['CTR', '6.0%']] as [$l, $v])
                                            <div class="rounded bg-white px-2 py-1.5 text-center ring-1 ring-slate-200">
                                                <span class="block text-[9px] font-medium uppercase text-slate-500">{{ $l }}</span>
                                                <span class="block font-semibold tabular-nums text-slate-900">{{ $v }}</span>
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
                                    <p class="mt-1 text-[11px] text-slate-700">Splits with /blog/seo-tools-guide · merge or canonicalize</p>
                                </div>
                                <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Striking distance</p>
                                    <p class="mt-1 text-[11px] text-slate-700">3 queries at pos 5–20 with below-curve CTR</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Content opportunities</p>
                                    <ul class="mt-1.5 space-y-1 text-[11px] text-slate-700">
                                        <li>• Add FAQ schema for "what is saas seo"</li>
                                        <li>• Internal link from /pricing (high authority)</li>
                                        <li>• Word count below median for top 10 (1,420 vs 2,100)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Sidebar features</p>
                            <ul class="mt-3 space-y-2.5 text-[13px] leading-6 text-slate-700">
                                <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span><span><strong>Target keyword field</strong> — set or change the focus query right from the editor; EBQ updates rank tracking and audits to match.</span></li>
                                <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span><span><strong>30-day search performance</strong> — clicks, impressions, position, CTR pulled from GSC for this exact URL.</span></li>
                                <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span><span><strong>Live rank + delta</strong> — current position for the target query with 7-day movement indicator.</span></li>
                                <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span><span><strong>Cannibalization detector</strong> — flags other URLs on your site competing for the same query and suggests merge or canonical.</span></li>
                                <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span><span><strong>Striking-distance hints</strong> — queries this URL ranks 5–20 for with below-curve CTR (the easiest wins).</span></li>
                                <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span><span><strong>Content opportunities</strong> — FAQ schema gaps, internal-link suggestions, word-count vs top 10 median.</span></li>
                                <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span><span><strong>One-click resubmit</strong> — after publishing or updating, push the URL to Google's Indexing API without leaving the post.</span></li>
                                <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span><span><strong>Open in EBQ</strong> — jump to the full page report (audits, backlinks, history) in a new tab.</span></li>
                            </ul>
                        </div>
                    </div>

                    {{-- ── 8.2 Posts list column ─────────────────── --}}
                    <h3 class="mt-12 text-lg font-semibold text-slate-900">8.2 Posts list column — performance at a glance</h3>
                    <p class="mt-2 text-[14px] leading-7 text-slate-600">A new column on <em>Posts → All Posts</em> and <em>Pages → All Pages</em> showing 30-day clicks, position, and a movement arrow. Sortable, filterable, no extra tab.</p>

                    {{-- Mockup: posts list column --}}
                    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-2.5">
                            <span class="text-[11px] font-medium text-slate-500">WordPress · Posts</span>
                        </div>
                        <table class="min-w-full text-[12px]">
                            <thead class="bg-slate-50/60 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Title</th>
                                    <th class="px-3 py-2 text-left font-semibold">Author</th>
                                    <th class="px-3 py-2 text-right font-semibold">EBQ · Clicks 30d</th>
                                    <th class="px-3 py-2 text-right font-semibold">EBQ · Pos</th>
                                    <th class="px-3 py-2 text-right font-semibold">7d Δ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ([
                                    ['SaaS SEO playbook', 'Jane', '1,284', '#4', '▲ 2', 'emerald'],
                                    ['Best SEO tools 2026', 'Mark', '842', '#7', '▼ 1', 'rose'],
                                    ['Keyword research basics', 'Jane', '218', '#11', '▲ 4', 'emerald'],
                                    ['What is rank tracking', 'Sam', '12', '#19', '–', 'slate'],
                                ] as [$t, $a, $c, $p, $d, $tone])
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-4 py-2.5 font-medium text-slate-800">{{ $t }}</td>
                                        <td class="px-3 py-2.5 text-slate-600">{{ $a }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $c }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-900">{{ $p }}</td>
                                        <td @class([
                                            'px-3 py-2.5 text-right tabular-nums font-semibold',
                                            'text-emerald-600' => $tone === 'emerald',
                                            'text-rose-600' => $tone === 'rose',
                                            'text-slate-500' => $tone === 'slate',
                                        ])>{{ $d }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <ul class="mt-5 space-y-2 text-[13px] leading-6 text-slate-700">
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Sort by any EBQ column to triage the entire archive in seconds.</li>
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Filter by post type, author, category — the EBQ columns respect every WP filter.</li>
                        <li class="flex gap-2.5"><span class="mt-1.5 h-1 w-1 flex-none rounded-full bg-slate-400"></span>Hide / show columns from the WP <em>Screen Options</em> menu; settings persist per user.</li>
                    </ul>

                    {{-- ── 8.3 Dashboard widget ──────────────────── --}}
                    <h3 class="mt-12 text-lg font-semibold text-slate-900">8.3 Dashboard widget — daily snapshot on WP login</h3>
                    <p class="mt-2 text-[14px] leading-7 text-slate-600">Lands on the WordPress admin dashboard. The first thing editors see when they log in: the action insights they should ship today.</p>

                    {{-- Mockup: dashboard widget --}}
                    <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">WordPress dashboard · EBQ at a glance</p>
                            <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-100">+8% w/w</span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                            @foreach ([['Clicks 7d', '4,180'], ['Avg pos', '8.2'], ['New backlinks', '6']] as [$l, $v])
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $l }}</p>
                                    <p class="mt-1 text-base font-semibold tabular-nums text-slate-900">{{ $v }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-[12px]">
                            <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Striking distance</p>
                                <p class="mt-1 text-slate-700">14 quick-win opportunities</p>
                            </div>
                            <div class="rounded-lg border border-amber-100 bg-amber-50/60 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700">Cannibalizations</p>
                                <p class="mt-1 text-slate-700">3 unresolved conflicts</p>
                            </div>
                            <div class="rounded-lg border border-rose-100 bg-rose-50/60 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-rose-700">Indexing fails</p>
                                <p class="mt-1 text-slate-700">2 URLs stuck</p>
                            </div>
                            <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Top movers</p>
                                <p class="mt-1 text-slate-700">/blog/saas-seo +186 clicks</p>
                            </div>
                        </div>
                    </div>

                    {{-- ── 8.4 Plugin settings ───────────────────── --}}
                    <h3 class="mt-12 text-lg font-semibold text-slate-900">8.4 Plugin settings — connection, sync, and updates</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Connection</p>
                            <ul class="mt-2 space-y-1.5 text-[13px] leading-6 text-slate-700">
                                <li>One-click <em>Connect to EBQ</em> via challenge-response handshake.</li>
                                <li>Live status badge (Connected / Reconnect needed / Revoked).</li>
                                <li><em>Disconnect</em> immediately revokes the per-site token in EBQ.</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Sync controls</p>
                            <ul class="mt-2 space-y-1.5 text-[13px] leading-6 text-slate-700">
                                <li>Force-refresh sidebar data for the current post.</li>
                                <li>Toggle posts list column on/off.</li>
                                <li>Toggle dashboard widget on/off per user role.</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Auto-updates</p>
                            <ul class="mt-2 space-y-1.5 text-[13px] leading-6 text-slate-700">
                                <li>Plugin checks EBQ for new releases on the WP cron schedule.</li>
                                <li>Optional auto-install for security/patch releases.</li>
                                <li>Manual update from <strong>Plugins</strong> at any time.</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Permissions</p>
                            <ul class="mt-2 space-y-1.5 text-[13px] leading-6 text-slate-700">
                                <li>Sidebar visible to Editors+; widget configurable per role.</li>
                                <li>Connect/Disconnect requires <code>manage_options</code> (Admin).</li>
                                <li>Posts column respects post-type capabilities.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- ── 8.5 Install in 4 steps ───────────────── --}}
                    <h3 class="mt-12 text-lg font-semibold text-slate-900">8.5 Install in four steps</h3>
                    <ol class="mt-3 space-y-2 text-[14px] leading-7 text-slate-700">
                        <li><span class="font-mono text-slate-400">1.</span> In EBQ, open <strong>Settings → WordPress</strong>. Click <em>Download plugin</em> — you'll get the latest packaged ZIP.</li>
                        <li><span class="font-mono text-slate-400">2.</span> In WordPress, go to <strong>Plugins → Add New → Upload Plugin</strong>. Upload the ZIP, then activate.</li>
                        <li><span class="font-mono text-slate-400">3.</span> In the WP plugin settings, click <em>Connect to EBQ</em>. You'll be redirected back to EBQ, pick the matching website, and approve.</li>
                        <li><span class="font-mono text-slate-400">4.</span> Open any post — the EBQ panel appears in the Gutenberg sidebar. The dashboard widget and posts-list column populate within a sync cycle.</li>
                    </ol>

                    {{-- ── 8.6 Security ──────────────────────────── --}}
                    <h3 class="mt-12 text-lg font-semibold text-slate-900">8.6 Security model</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Per-site tokens</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">Each connection issues a Sanctum token scoped to a single website. Stolen tokens grant read-only access to that site's EBQ data only — never your account or other websites.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Server-side only</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">Tokens live in <code>wp_options</code> on the server. They never touch browser JS, never appear in REST responses, and never leave WP except to call EBQ over HTTPS.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Challenge-response connect</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">The connect handshake uses a one-time nonce signed by both sides. A copy-pasted URL can't be replayed — and an attacker without WP admin can't initiate a connection.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Instant revoke</p>
                            <p class="mt-2 text-[13px] leading-6 text-slate-700">Click <em>Disconnect</em> in either WP or EBQ. The token is invalidated server-side immediately and the plugin goes offline on the next request.</p>
                        </div>
                    </div>
                </section>

                {{-- ── METRIC GLOSSARY ──────────────────────── --}}
                <section id="metric-glossary" class="not-prose mt-24 scroll-mt-24">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900">Metric glossary</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">Quick definitions for the metrics you'll see across EBQ. Open the table for the source and exact window.</p>

                    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <table class="min-w-full text-[13px]">
                            <thead class="bg-slate-50/60 text-[10.5px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-4 py-2.5 text-left font-semibold">Metric</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">Source</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">Definition</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ([
                                    ['Clicks', 'GSC', 'Number of times a user clicked your result on Google Search.'],
                                    ['Impressions', 'GSC', 'Number of times your URL appeared in search results.'],
                                    ['CTR', 'GSC', 'Clicks ÷ Impressions, expressed as a percentage.'],
                                    ['Avg position', 'GSC', 'Mean rank of your URL across queries where it appeared.'],
                                    ['Sessions', 'GA4', 'Distinct user visits in a 30-minute inactivity window.'],
                                    ['Users', 'GA4', 'Distinct user identifiers (cookie / consent-mode signals).'],
                                    ['LCP', 'CWV', 'Largest Contentful Paint — when the main content renders.'],
                                    ['CLS', 'CWV', 'Cumulative Layout Shift — visual stability score.'],
                                    ['INP', 'CWV', 'Interaction to Next Paint — input responsiveness.'],
                                    ['TBT', 'CWV', 'Total Blocking Time — JS-blocked main-thread time.'],
                                    ['FCP', 'CWV', 'First Contentful Paint — first text/image visible.'],
                                    ['TTFB', 'CWV', 'Time To First Byte — server response latency.'],
                                    ['Δ clicks (28d)', 'EBQ', 'Backlink target page: clicks 28d after — clicks 28d before first-seen.'],
                                    ['Z-score', 'EBQ', 'Deviation from the 28-day baseline mean, in standard deviations.'],
                                ] as [$m, $src, $def])
                                    <tr>
                                        <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $m }}</td>
                                        <td class="px-4 py-2.5 text-slate-600">
                                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600">{{ $src }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-700">{{ $def }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- ── TROUBLESHOOTING ──────────────────────── --}}
                <section id="troubleshooting" class="not-prose mt-24 scroll-mt-24">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900">Troubleshooting</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">The handful of issues that account for almost every support ticket — and the fastest way to resolve each.</p>

                    <div class="mt-6 space-y-4">
                        @foreach ([
                            [
                                'GSC sync returns zero rows',
                                'Property mismatch — the URL prefix in GSC must exactly match the canonical URL in EBQ (protocol + host + trailing slash).',
                                'Open <strong>Settings → Integrations</strong>, click <em>Reselect property</em>, and pick the correct prefix. Force a sync from the same screen.',
                            ],
                            [
                                'GA4 dropdown is empty after consent',
                                'You\'re signed into Google with an account that doesn\'t have GA4 permission for the property.',
                                'Sign out of Google, sign back in with the right account, then reconnect from <strong>Settings → Integrations</strong>.',
                            ],
                            [
                                'Audit fails with "fetch blocked"',
                                'robots.txt or a WAF rule (e.g., Cloudflare bot fight mode) is blocking the EBQ user-agent.',
                                'Allow <code>EBQAuditBot</code> in robots.txt and whitelist the EBQ IP range listed in <strong>Settings → Audit access</strong>.',
                            ],
                            [
                                'Indexing API resubmit returns "permission denied"',
                                'The Indexing scope wasn\'t granted, or the property isn\'t verified for the connected Google account.',
                                'Re-run the OAuth flow from <strong>Settings → Integrations</strong> and confirm the indexing checkbox is on. Verify the GSC property if needed.',
                            ],
                            [
                                'Backlink shows "removed" but the link is live',
                                'Source page renders the link via client-side JS, which EBQ\'s default verifier doesn\'t execute.',
                                'In <strong>Backlinks → Edit</strong>, switch the verifier to <em>Headless</em>. It runs the page through a real browser at the cost of a small per-link credit.',
                            ],
                            [
                                'No anomaly alerts despite a clear drop',
                                'Either the website has fewer than 14 days of baseline, or the drop didn\'t cross both gates (relative + z-score).',
                                'Check <strong>Reports → Alerts</strong> for the last evaluation. If baseline is short, alerts will start once 28 days are accumulated.',
                            ],
                        ] as [$title, $cause, $fix])
                            <details class="group rounded-xl border border-slate-200 bg-white open:bg-slate-50/40">
                                <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-4 text-[14px] font-semibold text-slate-900">
                                    {{ $title }}
                                    <span class="text-slate-400 transition group-open:rotate-180">⌄</span>
                                </summary>
                                <div class="border-t border-slate-200 px-5 py-4 text-[13px] leading-6 text-slate-700">
                                    <p><span class="font-semibold text-slate-900">Likely cause:</span> {{ $cause }}</p>
                                    <p class="mt-2"><span class="font-semibold text-slate-900">Fix:</span> {!! $fix !!}</p>
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>

                {{-- ── FAQ ──────────────────────────────────── --}}
                <section id="faq" class="not-prose mt-24 scroll-mt-24">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900">FAQ</h2>

                    <div class="mt-6 space-y-3">
                        @foreach ([
                            [
                                'How often does data refresh?',
                                'GSC and GA4 sync daily. Rank checks run on your plan\'s interval (typically daily on Pro, weekly on Starter). On-demand re-checks are available from any keyword or audit.',
                            ],
                            [
                                'Can I add multiple websites?',
                                'Yes. Each plan includes a website allowance; add more in <strong>Settings → Plan</strong>. Each website has its own integrations, recipients, and timezone.',
                            ],
                            [
                                'Is my Google data shared with third parties?',
                                'No. Tokens stay on the EBQ server, encrypted at rest. Reports are generated and delivered by EBQ; no analytics or attribution pixels are embedded.',
                            ],
                            [
                                'How accurate are the ranks?',
                                'Real SERPs are captured from a residential pool, segmented by country, device, language, and (optional) city. Position is recorded post-feature (i.e., your visible rank, not a synthetic one).',
                            ],
                            [
                                'Can I export raw data?',
                                'Yes. Every board offers CSV export. Reports support PDF (white-label on Agency). API access is available on Pro+ — see the API section in <strong>Settings</strong>.',
                            ],
                            [
                                'What happens if I disconnect Google?',
                                'Existing data is preserved. New syncs stop until you reconnect. Scheduled reports continue using cached data; alerts pause to avoid false signals.',
                            ],
                            [
                                'Do you support team access?',
                                'Yes — invite teammates with role-based permissions (Owner / Editor / Viewer / Reports-only). Configure in <strong>Team</strong>.',
                            ],
                        ] as [$q, $a])
                            <details class="group rounded-xl border border-slate-200 bg-white open:bg-slate-50/40">
                                <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-4 text-[14px] font-semibold text-slate-900">
                                    {{ $q }}
                                    <span class="text-slate-400 transition group-open:rotate-180">⌄</span>
                                </summary>
                                <div class="border-t border-slate-200 px-5 py-4 text-[13px] leading-6 text-slate-700">{!! $a !!}</div>
                            </details>
                        @endforeach
                    </div>
                </section>

                {{-- ── WEEKLY RHYTHM ────────────────────────── --}}
                <section id="weekly-rhythm" class="not-prose mt-24 scroll-mt-24">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900">A repeatable weekly rhythm</h2>
                    <p class="mt-4 text-[16px] leading-7 text-slate-600">Once setup is done, the loop is what produces compounding gains. Most teams run this in 30–45 minutes a week.</p>

                    <ol class="mt-6 space-y-3 text-[14px]">
                        @foreach ([
                            ['Mon · 5 min', 'Open the dashboard. Read the action insights panel and yesterday\'s anomaly alerts (if any).'],
                            ['Mon · 10 min', 'Triage striking-distance and cannibalization. Pick 1–3 actions with one-click ticket export.'],
                            ['Tue–Thu', 'Ship fixes. Re-audit each page and resubmit to Google from the audit view.'],
                            ['Thu · 5 min', 'Check new backlinks landed this week — verify and check the 28-day click delta on the target page.'],
                            ['Fri · 10 min', 'Read the scheduled weekly report. Note YoY direction and which actions actually moved the needle.'],
                            ['Fri · 5 min', 'Update the rolling SEO log with what shipped and the result. Builds a reviewable history.'],
                        ] as [$when, $what])
                            <li class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white px-5 py-3.5">
                                <span class="mt-0.5 inline-flex w-28 flex-none rounded-md bg-slate-900 px-2 py-1 text-center text-[11px] font-semibold text-white">{{ $when }}</span>
                                <span class="text-slate-700">{{ $what }}</span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>
        </div>
    </section>

    {{-- ── CTA ──────────────────────────────────────────────── --}}
    <section class="bg-slate-50/60 py-20 sm:py-24">
        <div class="mx-auto max-w-3xl px-6 text-center lg:px-8">
            <h2 class="text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Ready to run the loop on your data?</h2>
            <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-600">Connect your first website and complete the eight steps in under twenty minutes.</p>
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Start free</a>
                <a href="{{ route('features') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900">See features</a>
            </div>
        </div>
    </section>
</x-marketing.page>
