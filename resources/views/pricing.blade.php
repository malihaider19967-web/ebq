<x-marketing.page
    title="Pricing — EBQ"
    description="Simple, transparent EBQ pricing. Start free, scale as you grow. WordPress plugin always included."
    active="pricing"
>
    {{-- ── Hero ────────────────────────────────────────────────── --}}
    <section class="mx-auto max-w-7xl px-6 pb-12 pt-14 text-center lg:px-8 lg:pb-16 lg:pt-20">
        <p class="inline-flex items-center rounded-full border border-indigo-200/40 bg-indigo-500/15 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.2em] text-indigo-100">Simple pricing</p>
        <h1 class="mt-6 text-4xl font-semibold tracking-tight text-white sm:text-5xl">Pay for the sites you manage, nothing else.</h1>
        <p class="mx-auto mt-6 max-w-2xl text-base leading-7 text-slate-200 sm:text-lg">
            Every plan includes the EBQ WordPress plugin, the EBQ HQ dashboard, and unlimited team members. Annual subscriptions only — try free for a month before you commit.
        </p>
        <div class="mt-8 inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-medium text-slate-200">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden></span>
            1-month free trial · Then annual billing · Cancel during trial, no charge
        </div>
    </section>

    {{-- ── Pricing tiers ───────────────────────────────────────── --}}
    <section class="mx-auto max-w-7xl px-6 pb-20 lg:px-8 lg:pb-28">
        <div class="grid gap-6 lg:grid-cols-4">

            {{-- Free --}}
            <div class="flex flex-col rounded-2xl border border-white/10 bg-white/5 p-7">
                <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Free</h2>
                <p class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-4xl font-bold text-white">$0</span>
                    <span class="text-sm text-slate-300">forever</span>
                </p>
                <p class="mt-1 text-xs text-slate-400">No card required.</p>
                <p class="mt-3 text-sm text-slate-300">For personal sites and trial runs.</p>
                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> 1 connected website</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> WordPress plugin (live SEO score, schema generator, brief)</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Search Console performance + indexing dashboard</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> 5 tracked keywords</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> 10 page audits / month</li>
                    <li class="flex gap-2"><span class="text-slate-500">—</span> AI Writer (preview only)</li>
                    <li class="flex gap-2"><span class="text-slate-500">—</span> Topical-gap analysis</li>
                </ul>
                <a href="{{ route('register') }}" class="mt-7 inline-flex items-center justify-center rounded-md border border-white/20 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                    Start free
                </a>
            </div>

            {{-- Starter --}}
            <div class="flex flex-col rounded-2xl border border-white/10 bg-white/5 p-7">
                <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Starter</h2>
                <p class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-4xl font-bold text-white">$15</span>
                    <span class="text-sm text-slate-300">/mo</span>
                </p>
                <p class="mt-1 text-xs text-slate-400">$180 billed yearly · 1-month free trial</p>
                <p class="mt-3 text-sm text-slate-300">For one site you actively grow.</p>
                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Everything in Free</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> 1 website, 50 tracked keywords</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> 100 page audits / month</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Topical-gap analysis (top-5 SERP)</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> AI snippet rewrites + content brief</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Backlink monitoring (own domain)</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Email support</li>
                </ul>
                <a href="{{ route('register') }}?plan=starter" class="mt-7 inline-flex items-center justify-center rounded-md border border-white/20 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                    Start 1-month trial
                </a>
            </div>

            {{-- Pro (most popular) --}}
            <div class="relative flex flex-col rounded-2xl border-2 border-indigo-400/60 bg-gradient-to-br from-indigo-500/15 via-white/5 to-cyan-400/10 p-7 shadow-2xl shadow-indigo-500/20">
                <span class="absolute -top-3 right-6 inline-flex items-center rounded-full bg-gradient-to-r from-indigo-500 to-cyan-400 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white">Most popular</span>
                <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-200">Pro</h2>
                <p class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-4xl font-bold text-white">$39</span>
                    <span class="text-sm text-slate-200">/mo</span>
                </p>
                <p class="mt-1 text-xs text-slate-300">$468 billed yearly · 1-month free trial</p>
                <p class="mt-3 text-sm text-slate-200">For agencies and growth teams.</p>
                <ul class="mt-6 space-y-3 text-sm text-slate-100">
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Everything in Starter</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> 5 websites, 250 tracked keywords</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> 500 page audits / month</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> AI Writer (full draft, section-by-section)</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Competitor backlink prospecting</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Cross-site benchmarks + topical authority</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Quick-submit (Google Indexing API)</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Priority email + chat support</li>
                </ul>
                <a href="{{ route('register') }}?plan=pro" class="mt-7 inline-flex items-center justify-center rounded-md bg-white px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                    Start 1-month trial
                </a>
            </div>

            {{-- Agency --}}
            <div class="flex flex-col rounded-2xl border border-white/10 bg-white/5 p-7">
                <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Agency</h2>
                <p class="mt-3 flex items-baseline gap-1.5">
                    <span class="text-4xl font-bold text-white">$125</span>
                    <span class="text-sm text-slate-300">/mo</span>
                </p>
                <p class="mt-1 text-xs text-slate-400">$1,500 billed yearly · 1-month free trial</p>
                <p class="mt-3 text-sm text-slate-300">For agencies managing many clients.</p>
                <ul class="mt-6 space-y-3 text-sm text-slate-200">
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Everything in Pro</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> 25 websites, 1,500 tracked keywords</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> 2,500 page audits / month</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> White-label client reports (PDF)</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Bulk operations + batch URL submit</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> SSO + role-based access</li>
                    <li class="flex gap-2"><span class="text-emerald-400">✓</span> Dedicated success manager</li>
                </ul>
                <a href="mailto:sales@ebq.io?subject=Agency%20plan%20enquiry" class="mt-7 inline-flex items-center justify-center rounded-md border border-white/20 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                    Talk to sales
                </a>
            </div>
        </div>

        {{-- ── Add-ons / extras ──────────────────────────────────── --}}
        <div class="mt-12 rounded-2xl border border-white/10 bg-white/5 p-8">
            <h3 class="text-lg font-semibold text-white">Need more?</h3>
            <p class="mt-2 text-sm text-slate-300">Add-ons stack onto your annual subscription, billed alongside the next renewal.</p>
            <dl class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-sm font-semibold text-white">Extra website</dt>
                    <dd class="mt-1 text-sm text-slate-300">$96 / site / year</dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold text-white">Extra 100 keywords</dt>
                    <dd class="mt-1 text-sm text-slate-300">$48 / year</dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold text-white">Extra 500 audits</dt>
                    <dd class="mt-1 text-sm text-slate-300">$144 / year</dd>
                </div>
            </dl>
        </div>

        {{-- ── FAQ ───────────────────────────────────────────────── --}}
        <div class="mt-16">
            <h3 class="text-2xl font-semibold text-white">Pricing FAQ</h3>
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-white/10 bg-white/5 p-6">
                    <p class="font-semibold text-white">Is there a free trial?</p>
                    <p class="mt-2 text-sm text-slate-300">Yes — every paid plan starts with a 1-month free trial. Your card isn't charged until the trial ends, and you can cancel anytime during the trial without being billed.</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-6">
                    <p class="font-semibold text-white">Why annual only — no monthly billing?</p>
                    <p class="mt-2 text-sm text-slate-300">SEO is a long game and the value compounds month over month. Annual plans let us invest more in your account (deeper history, better caching, dedicated SERP capacity) at a price ~25% lower than monthly equivalents. The 1-month free trial gives you plenty of room to evaluate before committing.</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-6">
                    <p class="font-semibold text-white">Can I switch plans later?</p>
                    <p class="mt-2 text-sm text-slate-300">Yes — upgrade anytime; we charge the pro-rated difference for the rest of your annual term. Downgrades take effect at the next renewal so you keep what you paid for.</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-6">
                    <p class="font-semibold text-white">Do I need a credit card to sign up?</p>
                    <p class="mt-2 text-sm text-slate-300">Not for the Free plan. For paid trials we ask for a card upfront so the plan converts seamlessly when the trial ends — but the card is never charged before the 1-month trial completes, and you can cancel any time before then.</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-6">
                    <p class="font-semibold text-white">What counts as a "website"?</p>
                    <p class="mt-2 text-sm text-slate-300">A unique domain or subdomain you connect to EBQ. Each gets its own GSC sync, audit history, keyword tracker, and HQ dashboard.</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-6">
                    <p class="font-semibold text-white">Does the WordPress plugin cost extra?</p>
                    <p class="mt-2 text-sm text-slate-300">No. The plugin is included in every plan, including Free. Pro features inside the plugin (full AI Writer, topical gaps, brief generator) light up automatically when your account is on Pro or Agency.</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-6">
                    <p class="font-semibold text-white">Refunds?</p>
                    <p class="mt-2 text-sm text-slate-300">Yes — see our <a href="{{ route('refund-policy') }}" class="text-indigo-200 underline">Refund Policy</a> for the 30-day money-back terms.</p>
                </div>
            </div>
        </div>

        {{-- ── Bottom CTA ───────────────────────────────────────── --}}
        <div class="mt-16 rounded-2xl border border-white/10 bg-gradient-to-br from-indigo-500/20 via-white/5 to-cyan-400/10 p-10 text-center">
            <h3 class="text-2xl font-semibold text-white sm:text-3xl">Ready to ship better SEO?</h3>
            <p class="mx-auto mt-3 max-w-xl text-sm text-slate-200">Connect your first WordPress site in under two minutes. Free forever on the Free plan.</p>
            <div class="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">Start free</a>
                <a href="{{ route('features') }}" class="inline-flex items-center justify-center rounded-md border border-white/25 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">See features</a>
            </div>
        </div>
    </section>
</x-marketing.page>
