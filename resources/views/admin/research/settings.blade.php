<x-layouts.app>
    @php
        $allowlistText = is_array($settings['rollout_allowlist'] ?? null)
            ? implode(', ', $settings['rollout_allowlist'])
            : '';
    @endphp
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Research engine settings</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Runtime controls for the continuous-research engine. All changes take effect on the next scheduler tick / queued job — no redeploy needed.</p>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Queue snapshot --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (['queued','scanning','done','paused'] as $statusKey)
                <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500">Targets {{ $statusKey }}</div>
                    <div class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($queueDepth[$statusKey] ?? 0) }}</div>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.research.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Engine controls --}}
            <fieldset class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Engine controls</legend>

                <label class="mt-3 flex items-start gap-3">
                    <input type="checkbox" name="engine_paused" value="1" @checked(! empty($settings['engine_paused'])) class="mt-0.5 rounded border-slate-300">
                    <span>
                        <span class="block text-sm font-medium">Pause the entire engine</span>
                        <span class="block text-xs text-slate-500">Master kill-switch. While paused: scheduler tick is a no-op, no auto-discovery, no outlink fan-out, no onboarding-driven competitor discovery. Manually-dispatched scans still run if you trigger them from the scan form.</span>
                    </span>
                </label>

                <label class="mt-4 flex items-start gap-3">
                    <input type="checkbox" name="auto_discovery_disabled" value="1" @checked(! empty($settings['auto_discovery_disabled'])) class="mt-0.5 rounded border-slate-300">
                    <span>
                        <span class="block text-sm font-medium">Disable auto Serper-driven discovery</span>
                        <span class="block text-xs text-slate-500">Stops the engine calling Serper to find new competitor domains (CompetitorDiscoveryService and the website-onboarding hook). Scheduled scrapes of <em>already-queued</em> targets continue. Outlink-derived enqueues continue (no API cost). Useful when Serper budget is a concern.</span>
                    </span>
                </label>
            </fieldset>

            {{-- Pipeline behaviour --}}
            <fieldset class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Pipeline behaviour</legend>

                <label class="mt-3 flex items-start gap-3">
                    <input type="checkbox" name="auto_fetch_volume" value="1" @checked(! empty($settings['auto_fetch_volume'])) class="mt-0.5 rounded border-slate-300">
                    <span>
                        <span class="block text-sm font-medium">Auto-fetch keyword volumes from KeywordsEverywhere</span>
                        <span class="block text-xs text-slate-500">When on, EnrichKeywordJob calls KE for volume / CPC / competition (1 credit per keyword). Off by default to keep KE spend predictable. Manual `/research/keywords` lookups always work.</span>
                    </span>
                </label>

                <label class="mt-4 flex items-start gap-3">
                    <input type="checkbox" name="embeddings_enabled" value="1" @checked(! empty($settings['embeddings_enabled'])) class="mt-0.5 rounded border-slate-300">
                    <span>
                        <span class="block text-sm font-medium">Use Mistral embeddings for niche / cluster similarity</span>
                        <span class="block text-xs text-slate-500">Switches KeywordToNicheMapper + ClusteringService to embedding-cosine when MISTRAL_API_KEY is configured. Off → rule-based + Jaccard SERP overlap.</span>
                    </span>
                </label>
            </fieldset>

            {{-- Rollout --}}
            <fieldset class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Rollout gate</legend>

                <div class="mt-3">
                    <label class="text-sm font-medium">Mode</label>
                    <div class="mt-2 flex flex-wrap gap-3 text-xs">
                        @foreach (['ga' => 'GA — everyone with feature access', 'cohort' => 'Cohort — allowlist only', 'admin' => 'Admin — internal allowlist only'] as $value => $label)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="rollout_mode" value="{{ $value }}" @checked(($settings['rollout_mode'] ?? 'ga') === $value)>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4">
                    <label class="text-sm font-medium">Allowlist (website IDs)</label>
                    <input type="text" name="rollout_allowlist" value="{{ $allowlistText }}" placeholder="1, 2, 5, 17"
                        class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                    <p class="mt-1 text-[11px] text-slate-500">Comma- or space-separated. Used when rollout mode is cohort or admin.</p>
                </div>
            </fieldset>

            {{-- Quotas --}}
            <fieldset class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Per-website monthly quotas</legend>
                <p class="mt-2 text-[11px] text-slate-500">When usage exceeds these in a calendar month, ResearchQuotaService throws HTTP 402.</p>

                @php
                    $quotaFields = [
                        'keyword_lookup' => 'Keyword volume lookups (KE)',
                        'serp_fetch' => 'SERP fetches (Serper)',
                        'llm_call' => 'LLM calls (Mistral)',
                        'brief' => 'Content briefs',
                    ];
                @endphp

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($quotaFields as $key => $label)
                        <div>
                            <label class="text-sm font-medium">{{ $label }}</label>
                            <input type="number" name="quotas[{{ $key }}]" value="{{ $settings['quotas'][$key] ?? 0 }}" min="0"
                                class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        </div>
                    @endforeach
                </div>
            </fieldset>

            {{-- Scraper caps --}}
            <fieldset class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Scraper server-side ceilings</legend>
                <p class="mt-2 text-[11px] text-slate-500">Per-scan caps from the scan-create form are clamped at these ceilings. Prevents an admin from accidentally requesting a 100k-page crawl.</p>

                @php
                    $scraperFields = [
                        'ceiling_total_pages' => ['Max total pages', 10, 100000],
                        'ceiling_external_per_domain' => ['Max external pages per domain', 0, 1000],
                        'ceiling_depth' => ['Max crawl depth', 1, 20],
                        'timeout_seconds' => ['Job timeout (seconds)', 60, 21600],
                    ];
                @endphp

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($scraperFields as $key => [$label, $min, $max])
                        <div>
                            <label class="text-sm font-medium">{{ $label }}</label>
                            <input type="number" name="scraper[{{ $key }}]" value="{{ $settings['scraper'][$key] ?? '' }}" min="{{ $min }}" max="{{ $max }}"
                                class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">Save settings</button>
            </div>
        </form>

        {{-- Read-only diagnostics --}}
        <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Deploy-time diagnostics (read-only)</h2>
            <p class="mt-1 text-[11px] text-slate-500">These come from the Laravel <code>.env</code> + auto-detection and require a redeploy + queue restart to change.</p>
            <dl class="mt-3 grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                @foreach ($diagnostics as $name => $value)
                    <div class="flex items-center justify-between rounded-md bg-slate-50 px-2.5 py-1.5 dark:bg-slate-800/60">
                        <dt class="font-mono text-slate-500">{{ $name }}</dt>
                        <dd class="ml-2 truncate text-right font-mono">{{ $value !== '' ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
</x-layouts.app>
