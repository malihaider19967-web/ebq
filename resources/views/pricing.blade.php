<x-marketing.page
    title="Pricing — EBQ"
    description="Simple annual pricing for SEO teams. Free plan available. 1-month free trial on every paid plan."
    active="pricing"
>
    @php($freeMode = (bool) config('app.free', false))
    {{-- ── Hero ──────────────────────────────────────────────── --}}
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-6 py-20 text-center lg:px-8 lg:py-24">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Pricing</p>
            <h1 class="mx-auto mt-4 max-w-3xl text-balance text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                {{ $freeMode ? 'All Pro features are unlocked free for a limited time.' : 'Pay for the sites you manage. Nothing else.' }}
            </h1>
            <p class="mx-auto mt-5 max-w-2xl text-balance text-[17px] leading-8 text-slate-600">
                {{ $freeMode
                    ? 'Every account currently gets Pro capabilities at no cost during this promotional period.'
                    : 'Every plan includes the EBQ workspace, WordPress plugin, and unlimited team members. Annual subscriptions only — every paid plan starts with a 1-month free trial.' }}
            </p>
            <div class="mt-7 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-medium {{ $freeMode ? 'text-emerald-700' : 'text-slate-600' }}">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                {{ $freeMode ? 'Free Pro access for a limited time' : '1-month free trial · Cancel during trial, no charge' }}
            </div>
        </div>
    </section>

    <?php if ($freeMode): ?>
        <section class="bg-white py-16 sm:py-20">
            <div class="mx-auto max-w-4xl px-6 lg:px-8">
                <div class="rounded-3xl border border-emerald-200 bg-emerald-50/60 px-8 py-14 text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Limited-time offer</p>
                    <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        You currently have full Pro usage at no cost.
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600">
                        Create your account and use Pro features now. We will notify users in advance before standard pricing resumes.
                    </p>
                    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Start free</a>
                        <a href="{{ route('features') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900">See all features</a>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (! $freeMode): ?>
    {{-- ── Plan cards ───────────────────────────────────────── --}}
    <section class="bg-white py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-4">

                @php
                    $plans = [
                        [
                            'name' => 'Free',
                            'price' => '$0',
                            'suffix' => 'forever',
                            'caption' => 'No card required.',
                            'tagline' => 'For personal sites and trial runs.',
                            'features' => [
                                '1 connected website',
                                'WordPress plugin (full)',
                                'Search Console performance + indexing',
                                '5 tracked keywords',
                                '10 page audits / month',
                            ],
                            'excluded' => ['AI Writer (preview only)', 'Topical-gap analysis'],
                            'cta' => ['Start free', route('register'), 'ghost'],
                            'highlight' => false,
                        ],
                        [
                            'name' => 'Starter',
                            'price' => '$15',
                            'suffix' => '/mo',
                            'caption' => '$180 billed yearly',
                            'tagline' => 'For one site you actively grow.',
                            'features' => [
                                'Everything in Free',
                                '50 tracked keywords',
                                '100 page audits / month',
                                'Topical-gap analysis (top-5 SERP)',
                                'AI snippet rewrites + content brief',
                                'Backlink monitoring (own domain)',
                            ],
                            'excluded' => [],
                            'cta' => ['Start 1-month trial', route('register').'?plan=starter', 'ghost'],
                            'highlight' => false,
                        ],
                        [
                            'name' => 'Pro',
                            'price' => '$39',
                            'suffix' => '/mo',
                            'caption' => '$468 billed yearly',
                            'tagline' => 'For agencies and growth teams.',
                            'features' => [
                                'Everything in Starter',
                                '5 websites, 250 tracked keywords',
                                '500 page audits / month',
                                'AI Writer (full draft)',
                                'Competitor backlink prospecting',
                                'Cross-site benchmarks',
                                'Quick-submit (Google Indexing API)',
                                'Priority email + chat support',
                            ],
                            'excluded' => [],
                            'cta' => ['Start 1-month trial', route('register').'?plan=pro', 'primary'],
                            'highlight' => true,
                        ],
                        [
                            'name' => 'Agency',
                            'price' => '$125',
                            'suffix' => '/mo',
                            'caption' => '$1,500 billed yearly',
                            'tagline' => 'For agencies managing many clients.',
                            'features' => [
                                'Everything in Pro',
                                '25 websites, 1,500 tracked keywords',
                                '2,500 page audits / month',
                                'White-label client reports (PDF)',
                                'Bulk operations + batch URL submit',
                                'SSO + role-based access',
                                'Dedicated success manager',
                            ],
                            'excluded' => [],
                            'cta' => ['Talk to sales', 'mailto:sales@ebq.io?subject=Agency%20plan%20enquiry', 'ghost'],
                            'highlight' => false,
                        ],
                    ];
                @endphp

                @foreach ($plans as $plan)
                    <div @class([
                        'relative flex flex-col rounded-2xl border bg-white p-6',
                        'border-slate-900 shadow-[0_24px_60px_-24px_rgba(15,23,42,0.25)]' => $plan['highlight'],
                        'border-slate-200' => ! $plan['highlight'],
                    ])>
                        @if ($plan['highlight'])
                            <span class="absolute -top-3 left-6 inline-flex items-center rounded-full bg-slate-900 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white">Most popular</span>
                        @endif

                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $plan['name'] }}</p>
                        <div class="mt-4 flex items-baseline gap-1.5">
                            <span class="text-4xl font-semibold tracking-tight text-slate-900">{{ $plan['price'] }}</span>
                            <span class="text-sm text-slate-500">{{ $plan['suffix'] }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $plan['caption'] }}</p>
                        <p class="mt-4 text-sm text-slate-600">{{ $plan['tagline'] }}</p>

                        <ul class="mt-6 space-y-2.5 text-[13px] text-slate-700">
                            @foreach ($plan['features'] as $f)
                                <li class="flex gap-2.5">
                                    <svg class="mt-0.5 h-4 w-4 flex-none text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    <span>{{ $f }}</span>
                                </li>
                            @endforeach
                            @foreach ($plan['excluded'] as $e)
                                <li class="flex gap-2.5 text-slate-400">
                                    <svg class="mt-0.5 h-4 w-4 flex-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                                    <span>{{ $e }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ $plan['cta'][1] }}" @class([
                            'mt-7 inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                            'bg-slate-900 text-white hover:bg-slate-800' => $plan['cta'][2] === 'primary',
                            'border border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:text-slate-900' => $plan['cta'][2] === 'ghost',
                        ])>
                            {{ $plan['cta'][0] }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Comparison table ─────────────────────────────────── --}}
    <section class="bg-slate-50/60 py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Compare plans</p>
                <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">All features at a glance.</h2>
            </div>

            <div class="mt-10 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/60 text-left">
                                <th class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Feature</th>
                                @foreach (['Free', 'Starter', 'Pro', 'Agency'] as $col)
                                    <th class="px-5 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ([
                                ['Connected websites', '1', '1', '5', '25'],
                                ['Tracked keywords', '5', '50', '250', '1,500'],
                                ['Page audits / month', '10', '100', '500', '2,500'],
                                ['Cross-signal insights (6 boards)', true, true, true, true],
                                ['WordPress plugin', true, true, true, true],
                                ['AI snippet rewrites', false, true, true, true],
                                ['AI Writer (full draft)', false, false, true, true],
                                ['Competitor backlink prospecting', false, false, true, true],
                                ['White-label PDF reports', false, false, false, true],
                                ['SSO + RBAC', false, false, false, true],
                                ['Priority support', false, false, true, true],
                                ['Dedicated success manager', false, false, false, true],
                            ] as $row)
                                <tr>
                                    <td class="px-5 py-3.5 font-medium text-slate-800">{{ $row[0] }}</td>
                                    @foreach (array_slice($row, 1) as $val)
                                        <td class="px-5 py-3.5 text-center">
                                            @if ($val === true)
                                                <svg class="mx-auto h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                            @elseif ($val === false)
                                                <svg class="mx-auto h-4 w-4 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                                            @else
                                                <span class="font-mono text-[13px] tabular-nums text-slate-700">{{ $val }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Add-ons ──────────────────────────────────────────── --}}
    <section class="bg-white py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 sm:p-10">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Add-ons</p>
                        <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Need more capacity?</h3>
                        <p class="mt-2 text-sm text-slate-600">Stack add-ons onto your annual plan. Billed alongside the next renewal.</p>
                    </div>
                </div>
                <dl class="mt-8 grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-3">
                    <div class="bg-white p-5">
                        <dt class="text-sm font-semibold text-slate-900">Extra website</dt>
                        <dd class="mt-1 text-sm text-slate-600">$96 / site / year</dd>
                    </div>
                    <div class="bg-white p-5">
                        <dt class="text-sm font-semibold text-slate-900">Extra 100 keywords</dt>
                        <dd class="mt-1 text-sm text-slate-600">$48 / year</dd>
                    </div>
                    <div class="bg-white p-5">
                        <dt class="text-sm font-semibold text-slate-900">Extra 500 audits</dt>
                        <dd class="mt-1 text-sm text-slate-600">$144 / year</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    {{-- ── Pricing FAQ ──────────────────────────────────────── --}}
    <section class="bg-slate-50/60 py-16 sm:py-20">
        <div class="mx-auto max-w-3xl px-6 lg:px-8">
            <div class="text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">FAQ</p>
                <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Pricing questions, answered.</h2>
            </div>

            <div class="mt-10 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
                @foreach ([
                    ['Is there a free trial?', 'Yes — every paid plan starts with a 1-month free trial. Your card is not charged until the trial ends, and you can cancel anytime during the trial without being billed.'],
                    ['Why annual only?', 'Annual plans let us invest more in your account (deeper history, better caching, dedicated SERP capacity) at a price ~25% lower than monthly equivalents. The 1-month trial gives plenty of room to evaluate.'],
                    ['Can I switch plans later?', 'Yes. Upgrades pro-rate immediately for the rest of your annual term. Downgrades take effect at the next renewal so you keep what you paid for.'],
                    ['What counts as a website?', 'A unique domain or subdomain you connect to EBQ. Each gets its own GSC sync, audit history, keyword tracker, and dashboard.'],
                    ['Refunds?', 'See our refund policy for the 30-day money-back terms.'],
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
    <?php endif; ?>

    {{-- ── CTA ──────────────────────────────────────────────── --}}
    <section class="bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-4xl px-6 lg:px-8">
            <div class="rounded-3xl border border-slate-200 bg-slate-50/60 px-6 py-14 text-center sm:px-12">
                <h2 class="text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Ready to ship better SEO?</h2>
                <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-600">Connect your first website in under two minutes. Free forever on the Free plan.</p>
                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Start free</a>
                    <a href="{{ route('features') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900">See features</a>
                </div>
            </div>
        </div>
    </section>
</x-marketing.page>
