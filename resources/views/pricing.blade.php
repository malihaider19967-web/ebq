@php
    $free          = (bool) config('app.free');
    // CTAs land logged-out users on /register?plan=X (where store()
    // bounces them to /billing/checkout right after auth) and existing
    // logged-in users straight onto /billing/checkout?plan=X. Either way
    // payment happens before onboarding.
    $authed        = auth()->check();
    $ctaForPlan    = function (string $slug) use ($authed) {
        return $authed
            ? route('billing.checkout', ['plan' => $slug])
            : route('register', ['plan' => $slug]);
    };
    $registerUrl   = route('register');
    $featuresUrl   = route('features');
    $contactUrl    = route('contact');
    $refundUrl     = route('refund-policy');
    $salesEmail    = 'sales@ebq.io';
    $salesMailto   = 'mailto:' . $salesEmail . '?subject=Agency%20plan%20enquiry';

    $heroEyebrow   = $free ? 'Limited-time promotion' : 'Pricing';
    $heroTitle     = $free
        ? 'Free for a limited time, then just $5/month.'
        : 'Pay for the sites you manage. Nothing else.';
    $heroSub       = $free
        ? 'Every account currently gets full Pro capabilities at no cost during this promotional period. When the promotion ends, plans start at just $5/month — and we will let you know well before anything changes.'
        : 'Every plan includes the EBQ workspace, WordPress plugin, and unlimited team members. Annual subscriptions only — every paid plan starts with a 1-month free trial.';
    $heroBadge     = $free
        ? 'Free now · then from $5/month'
        : '1-month free trial · Cancel during trial, no charge';

    // Pull plans straight from the DB — `Admin\PlanController` keeps
    // pricing, feature toggles, and quotas in sync without a deploy.
    // The Plan model + seeder ship the canonical 4-tier set; the
    // `Plan::ordered()` scope filters to active plans in display_order.
    $planRows = \App\Models\Plan::ordered()->get();

    // Display copy for the 8 plugin features. The pricing card's
    // "Includes:" list is auto-generated from `featureMap()` — every
    // checked flag emits its label here. Keys match Plan::FEATURE_KEYS.
    $featureCopy = [
        'live_audit'       => 'Live SEO score & audit',
        'hq'               => 'EBQ HQ rank tracker + performance',
        'redirects'        => '404-monitor + redirects manager',
        'dashboard_widget' => 'WordPress dashboard widget',
        'post_column'      => 'Posts-list EBQ score column',
        'ai_inline'        => 'AI inline edits (// slash commands)',
        'chatbot'          => 'EBQ Assistant chatbot',
        'ai_writer'        => 'AI Writer (full-draft generation)',
    ];

    // Pretty-format the per-plan API caps into 1-line strings for the
    // "Includes:" list. Null leaf = unlimited, but on the marketing
    // page we just omit unlimited rows (paid plans should never be
    // unlimited on every limit anyway).
    $apiLimitCopy = function (?array $limits): array {
        if (! is_array($limits)) {
            return [];
        }
        $out = [];
        $rt  = $limits['rank_tracker']['max_active_keywords'] ?? null;
        if ($rt !== null) {
            $out[] = number_format((int) $rt) . ' tracked keywords';
        }
        $ke  = $limits['keywords_everywhere']['monthly_credits'] ?? null;
        if ($ke !== null) {
            $out[] = number_format((int) $ke) . ' keyword research credits / month';
        }
        $ser = $limits['serper']['monthly_calls'] ?? null;
        if ($ser !== null) {
            $out[] = number_format((int) $ser) . ' SERP fetches / month';
        }
        $mis = $limits['mistral']['monthly_tokens'] ?? null;
        if ($mis !== null) {
            $out[] = number_format((int) $mis) . ' AI tokens / month';
        }
        return $out;
    };

    $planStyleFor = function (string $slug): array {
        return match ($slug) {
            'free'    => ['cta_label' => 'Start free',           'cta_style' => 'ghost'],
            'agency'  => ['cta_label' => 'Talk to sales',        'cta_style' => 'ghost'],
            default   => ['cta_label' => 'Start 1-month trial',  'cta_style' => 'primary'],
        };
    };

    // Free + Pro are single-site personal plans — they should read "Personal"
    // for the connected-websites entitlement, not "unlimited", regardless of
    // the stored max_websites value.
    $personalSlugs = ['free', 'pro'];
    $websitesCell = function (\App\Models\Plan $p) use ($personalSlugs) {
        if (in_array($p->slug, $personalSlugs, true)) {
            return 'Personal';
        }
        return $p->max_websites === null ? '∞' : (string) (int) $p->max_websites;
    };
    $websitesBullet = function (\App\Models\Plan $p) use ($personalSlugs) {
        if (in_array($p->slug, $personalSlugs, true)) {
            return 'Personal website (single site)';
        }
        if ($p->max_websites === null) {
            return 'Unlimited connected websites';
        }
        return $p->max_websites === 1
            ? '1 connected website'
            : (int) $p->max_websites . ' connected websites';
    };

    $plans = $planRows->map(function (\App\Models\Plan $p) use ($ctaForPlan, $salesMailto, $registerUrl, $featureCopy, $apiLimitCopy, $planStyleFor, $websitesBullet) {
        $slug    = (string) $p->slug;
        $monthly = (int) $p->price_monthly_usd;
        $yearly  = (int) $p->price_yearly_usd;
        $style   = $planStyleFor($slug);

        $featureMap = $p->featureMap();
        $autoBullets = [];
        $autoBullets[] = $websitesBullet($p);
        $autoBullets = array_merge($autoBullets, $apiLimitCopy($p->api_limits));
        foreach ($featureCopy as $key => $label) {
            if (($featureMap[$key] ?? false) === true) {
                $autoBullets[] = $label;
            }
        }

        $excluded = [];
        foreach ($featureCopy as $key => $label) {
            if (($featureMap[$key] ?? false) === false) {
                $excluded[] = $label;
            }
        }

        // Hand-written marketing bullets, each optionally carrying an
        // explainer video. `feature_videos` is a sparse index => URL map
        // aligned with `features`; we resolve each to a bare YouTube ID
        // so the template only ever embeds the privacy-friendly /embed
        // form and never trusts a raw URL.
        $rawBullets = is_array($p->features) ? array_values($p->features) : [];
        $bulletVideos = is_array($p->feature_videos ?? null) ? $p->feature_videos : [];
        $featureItems = [];
        foreach ($rawBullets as $i => $bullet) {
            $videoUrl = $bulletVideos[(string) $i] ?? ($bulletVideos[$i] ?? null);
            $featureItems[] = [
                'text' => $bullet,
                'video_id' => \App\Models\Plan::youtubeId($videoUrl),
            ];
        }

        return [
            'slug'      => $slug,
            'name'      => (string) $p->name,
            'price'     => $monthly > 0 ? '$' . number_format($monthly) : '$0',
            'suffix'    => $monthly > 0 ? '/mo' : 'forever',
            'caption'   => $yearly > 0
                ? '$' . number_format($yearly) . ' billed yearly'
                : 'No card required.',
            'tagline'   => (string) ($p->tagline ?? ''),
            'features'  => $featureItems,
            // Auto-generated entitlement bullets driven by plan_features
            // + api_limits + max_websites. Always shown above the
            // hand-written marketing bullets.
            'includes'  => $autoBullets,
            'excluded'  => $excluded,
            'cta_label' => $style['cta_label'],
            'cta_url'   => $slug === 'free'
                ? $registerUrl
                : ($slug === 'agency' ? $salesMailto : $ctaForPlan($slug)),
            'cta_style' => $style['cta_style'],
            'highlight' => (bool) $p->is_highlighted,
        ];
    })->all();

    // Auto-build the comparison matrix from the same DB rows so a new
    // tier added in /admin/plans appears here without a deploy.
    $compareRows = [];
    $compareRows[] = array_merge(['Connected websites'], $planRows->map(fn ($p) => $websitesCell($p))->all());
    $compareRows[] = array_merge(['Tracked keywords'], $planRows->map(fn ($p) => isset($p->api_limits['rank_tracker']['max_active_keywords']) ? number_format((int) $p->api_limits['rank_tracker']['max_active_keywords']) : '—')->all());
    $compareRows[] = array_merge(['SERP fetches / month'], $planRows->map(fn ($p) => isset($p->api_limits['serper']['monthly_calls']) ? number_format((int) $p->api_limits['serper']['monthly_calls']) : '—')->all());
    foreach ($featureCopy as $key => $label) {
        $compareRows[] = array_merge([$label], $planRows->map(fn ($p) => (bool) ($p->featureMap()[$key] ?? false))->all());
    }
    $compareColHeaders = $planRows->pluck('name')->all();

    $addOns = [
        ['Extra website',          '$96 / site / year'],
        ['Extra 100 keywords',     '$48 / year'],
        ['Extra 500 audits',       '$144 / year'],
    ];

    $faqs = [
        ['Is there a free trial?',           'Yes — every paid plan starts with a 1-month free trial. Your card is not charged until the trial ends, and you can cancel anytime during the trial without being billed.'],
        ['Why annual only?',                 'Annual plans let us invest more in your account (deeper history, better caching, dedicated SERP capacity) at a price ~25% lower than monthly equivalents. The 1-month trial gives plenty of room to evaluate.'],
        ['Can I switch plans later?',        'Yes. Upgrades pro-rate immediately for the rest of your annual term. Downgrades take effect at the next renewal so you keep what you paid for.'],
        ['What counts as a website?',        'A unique domain or subdomain you connect to EBQ. Each gets its own GSC sync, audit history, keyword tracker, and dashboard.'],
        ['Do you offer refunds?',            'Yes — see our refund policy for the 30-day money-back terms.'],
        ['Which payment methods do you accept?', 'All major credit and debit cards via our PCI-compliant payment processor. Invoicing is available on the Agency plan.'],
        ['Do prices include tax?',           'Prices shown exclude applicable VAT/GST. Local taxes are calculated at checkout based on your billing country.'],
    ];

    $trustItems = [
        ['title' => '30-day money-back', 'sub' => 'Full refund if EBQ is not a fit.'],
        ['title' => 'Cancel anytime',    'sub' => 'No long-term contracts.'],
        ['title' => 'Secure billing',    'sub' => 'PCI-compliant card processor.'],
        ['title' => 'GDPR & SOC 2-aligned', 'sub' => 'Privacy-first data handling.'],
    ];

    $jsonLd = [
        '@context'      => 'https://schema.org',
        '@type'         => 'Product',
        'name'          => 'EBQ',
        'description'   => 'EBQ is an SEO operations platform combining rankings, audits, backlinks, and AI content tools.',
        'brand'         => ['@type' => 'Brand', 'name' => 'EBQ'],
        'offers'        => [
            '@type'         => 'AggregateOffer',
            'priceCurrency' => 'USD',
            'lowPrice'      => (string) ($planRows->min('price_monthly_usd') ?? 0),
            'highPrice'     => (string) ($planRows->max('price_monthly_usd') ?? 0),
            'offerCount'    => count($plans),
            'availability'  => 'https://schema.org/InStock',
            'url'           => url()->current(),
        ],
    ];

    // FAQPage from the visible Q&As below, plus a breadcrumb trail.
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => [
            '@type' => 'Question',
            'name' => $f[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
        ], $faqs),
    ];
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('landing')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Pricing', 'item' => route('pricing')],
        ],
    ];
    $pricingJsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
@endphp

<x-marketing.page
    title="EBQ Pricing | Simple Plans for Advanced AI & SEO Audits"
    description="View EBQ pricing plans. Scale your traffic with a 47-tool AI studio, automated rank tracking, and deep multi-site SEO audits. Try it risk-free today!"
    active="pricing"
>
    {{-- Page-specific structured data: product offers, FAQ, breadcrumb. --}}
    <x-slot:schema>
        <script type="application/ld+json">{!! json_encode($jsonLd, $pricingJsonFlags) !!}</script>
        <script type="application/ld+json">{!! json_encode($faqSchema, $pricingJsonFlags) !!}</script>
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, $pricingJsonFlags) !!}</script>
    </x-slot:schema>
    {{-- ── Hero ──────────────────────────────────────────────── --}}
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-6 py-20 text-center lg:px-8 lg:py-24">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] {{ $free ? 'text-emerald-700' : 'text-slate-500' }}">{{ $heroEyebrow }}</p>
            <h1 class="mx-auto mt-4 max-w-3xl text-balance text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                {{ $heroTitle }}
            </h1>
            <p class="mx-auto mt-5 max-w-2xl text-balance text-[17px] leading-8 text-slate-600">
                {{ $heroSub }}
            </p>
            <div class="mt-7 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-medium {{ $free ? 'text-emerald-700' : 'text-slate-600' }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $free ? 'bg-emerald-500' : 'bg-emerald-500' }}"></span>
                {{ $heroBadge }}
            </div>

            @if (! $free)
                <div class="mt-7 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ $registerUrl }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">Start free</a>
                    <a href="#plans" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">Compare plans</a>
                </div>
            @endif
        </div>
    </section>

    @if ($free)
        {{-- ── Free promo strip (compact, keeps the table high) ──── --}}
        <section class="border-b border-emerald-200 bg-emerald-50/60">
            <div class="mx-auto max-w-4xl px-6 py-5 text-center lg:px-8">
                <p class="text-sm font-semibold text-emerald-800">
                    Free for a limited time — then just&nbsp;$5/month.
                    <span class="font-normal text-emerald-700">Every paid feature is unlocked now at no cost; we’ll give 30 days’ notice before pricing starts.</span>
                </p>
            </div>
        </section>
    @endif

        {{-- ── Plan cards ───────────────────────────────────────── --}}
        <section id="plans" class="bg-white pt-10 pb-16 sm:pt-12">
            <div class="mx-auto max-w-6xl px-6 lg:px-8">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
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
                                {{-- Auto-generated "Includes:" list driven by
                                     plan_features + api_limits + max_websites
                                     in /admin/plans. Always rendered before
                                     the marketing bullets so the entitlement
                                     truth is front-and-centre. --}}
                                @foreach ($plan['includes'] as $feature)
                                    <li class="flex gap-2.5">
                                        <svg class="mt-0.5 h-4 w-4 flex-none text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                                @foreach ($plan['features'] as $feature)
                                    <li class="flex gap-2.5">
                                        <svg class="mt-0.5 h-4 w-4 flex-none text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        @if ($feature['video_id'])
                                            <button type="button"
                                                    data-ebq-video="{{ $feature['video_id'] }}"
                                                    aria-haspopup="dialog"
                                                    class="group/vid inline-flex items-start gap-1 text-left text-slate-700 underline decoration-dotted decoration-slate-400 underline-offset-2 transition hover:text-slate-900 hover:decoration-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-1">
                                                <span>{{ $feature['text'] }}</span>
                                                <svg class="mt-0.5 h-3.5 w-3.5 flex-none text-slate-400 transition group-hover/vid:text-slate-900" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                                                <span class="sr-only">— play video</span>
                                            </button>
                                        @else
                                            <span>{{ $feature['text'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                                @foreach ($plan['excluded'] as $excluded)
                                    <li class="flex gap-2.5 text-slate-400">
                                        <svg class="mt-0.5 h-4 w-4 flex-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                                        <span>{{ $excluded }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            <a href="{{ $plan['cta_url'] }}"
                               aria-label="{{ $plan['cta_label'] }} — {{ $plan['name'] }} plan"
                               @class([
                                'mt-7 inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
                                'bg-slate-900 text-white hover:bg-slate-800 focus-visible:ring-slate-900' => $plan['cta_style'] === 'primary',
                                'border border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:text-slate-900 focus-visible:ring-slate-300' => $plan['cta_style'] === 'ghost',
                            ])>
                                {{ $plan['cta_label'] }}
                            </a>
                        </div>
                    @endforeach
                </div>

                <p class="mt-8 text-center text-xs text-slate-500">
                    Prices in USD, billed annually. Local taxes (VAT/GST) calculated at checkout. Need monthly billing for procurement?
                    <a href="{{ $contactUrl }}" class="font-medium text-slate-700 underline-offset-2 hover:text-slate-900 hover:underline">Get in touch</a>.
                </p>
            </div>
        </section>

        {{-- ── Trust strip ──────────────────────────────────────── --}}
        <section class="border-y border-slate-200 bg-slate-50/60 py-10">
            <div class="mx-auto max-w-6xl px-6 lg:px-8">
                <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($trustItems as $item)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex h-7 w-7 flex-none items-center justify-center rounded-full bg-white ring-1 ring-slate-200">
                                <svg class="h-3.5 w-3.5 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $item['title'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-600">{{ $item['sub'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- ── Comparison table ─────────────────────────────────── --}}
        <section class="bg-white py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Compare plans</p>
                    <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">All features at a glance.</h2>
                    <p class="mt-3 text-sm text-slate-600">Same workspace, same plugin, same data quality on every plan.</p>
                </div>

                <div class="mt-10 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Feature comparison across EBQ plans</caption>
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50/60 text-left">
                                    <th scope="col" class="px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Feature</th>
                                    @foreach ($compareColHeaders as $col)
                                        <th scope="col" class="px-5 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $col }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @foreach ($compareRows as $row)
                                    <tr>
                                        <th scope="row" class="px-5 py-3.5 text-left font-medium text-slate-800">{{ $row[0] }}</th>
                                        @foreach (array_slice($row, 1) as $val)
                                            <td class="px-5 py-3.5 text-center">
                                                @if ($val === true)
                                                    <span class="sr-only">Included</span>
                                                    <svg class="mx-auto h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                                @elseif ($val === false)
                                                    <span class="sr-only">Not included</span>
                                                    <svg class="mx-auto h-4 w-4 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
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
        <section class="bg-slate-50/60 py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-6 lg:px-8">
                <div class="rounded-2xl border border-slate-200 bg-white p-8 sm:p-10">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Add-ons</p>
                            <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Need more capacity?</h3>
                            <p class="mt-2 text-sm text-slate-600">Stack add-ons onto your annual plan. Billed alongside the next renewal.</p>
                        </div>
                        <a href="{{ $contactUrl }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900">
                            Custom volume? Talk to us
                        </a>
                    </div>
                    <dl class="mt-8 grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-3">
                        @foreach ($addOns as [$name, $price])
                            <div class="bg-white p-5">
                                <dt class="text-sm font-semibold text-slate-900">{{ $name }}</dt>
                                <dd class="mt-1 text-sm text-slate-600">{{ $price }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </section>

        {{-- ── Pricing FAQ ──────────────────────────────────────── --}}
        <section class="bg-white py-16 sm:py-20">
            <div class="mx-auto max-w-3xl px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">FAQ</p>
                    <h2 class="mt-3 text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Pricing questions, answered.</h2>
                </div>

                <div class="mt-10 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white">
                    @foreach ($faqs as [$question, $answer])
                        <details class="group p-6 [&_summary::-webkit-details-marker]:hidden">
                            <summary class="flex cursor-pointer items-center justify-between gap-3 text-[15px] font-semibold text-slate-900">
                                <span>{{ $question }}</span>
                                <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-slate-100 text-slate-600 transition group-open:rotate-45 group-open:bg-slate-900 group-open:text-white">
                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                </span>
                            </summary>
                            <p class="mt-3 text-[14px] leading-7 text-slate-600">
                                {{ $answer }}
                                @if (str_contains(strtolower($question), 'refund'))
                                    <a href="{{ $refundUrl }}" class="font-medium text-slate-700 underline-offset-2 hover:text-slate-900 hover:underline">Read the refund policy</a>.
                                @endif
                            </p>
                        </details>
                    @endforeach
                </div>

                <p class="mt-8 text-center text-sm text-slate-600">
                    Still have a question?
                    <a href="{{ $contactUrl }}" class="font-semibold text-slate-900 underline-offset-2 hover:underline">Contact us</a>
                    — we usually reply the same business day.
                </p>
            </div>
        </section>

    {{-- ── CTA ──────────────────────────────────────────────── --}}
    <section class="bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-4xl px-6 lg:px-8">
            <div class="rounded-3xl border border-slate-200 bg-slate-50/60 px-6 py-14 text-center sm:px-12">
                <h2 class="text-balance text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                    {{ $free ? 'Claim your free Pro access today.' : 'Ready to ship better SEO?' }}
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-600">
                    {{ $free
                        ? 'Sign up in under two minutes and get every Pro feature unlocked while the promotion lasts.'
                        : 'Connect your first website in under two minutes. Free forever on the Free plan.' }}
                </p>
                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ $registerUrl }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">{{ $free ? 'Start free Pro access' : 'Start free' }}</a>
                    <a href="{{ $featuresUrl }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">See features</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Bullet explainer-video modal ─────────────────────────
         Single shared dialog reused by every "play video" bullet. The
         iframe src is only set on open (with autoplay=1) and cleared on
         close so audio stops the moment the modal is dismissed. --}}
    <div id="ebq-video-modal"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/80 p-4 backdrop-blur-sm"
         role="dialog" aria-modal="true" aria-label="Feature video">
        <div class="relative w-full max-w-3xl">
            <button type="button" id="ebq-video-close"
                    class="absolute -top-9 right-0 inline-flex items-center gap-1 text-sm font-medium text-white/90 transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                    aria-label="Close video">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                Close
            </button>
            <div class="aspect-video w-full overflow-hidden rounded-xl bg-black shadow-2xl ring-1 ring-white/10">
                <iframe id="ebq-video-frame" class="h-full w-full" src="" title="Feature video"
                        loading="lazy" referrerpolicy="strict-origin-when-cross-origin"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen></iframe>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('ebq-video-modal');
            var frame = document.getElementById('ebq-video-frame');
            var closeBtn = document.getElementById('ebq-video-close');
            if (!modal || !frame || !closeBtn) {
                return;
            }

            function openVideo(id) {
                if (!/^[A-Za-z0-9_-]{11}$/.test(id)) {
                    return;
                }
                frame.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.style.overflow = 'hidden';
                closeBtn.focus();
            }

            function closeVideo() {
                frame.src = '';
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
            }

            document.querySelectorAll('[data-ebq-video]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openVideo(btn.getAttribute('data-ebq-video'));
                });
            });

            closeBtn.addEventListener('click', closeVideo);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    closeVideo();
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeVideo();
                }
            });
        })();
    </script>
</x-marketing.page>
