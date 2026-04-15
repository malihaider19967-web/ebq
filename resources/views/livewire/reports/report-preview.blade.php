<div class="space-y-6">
    {{-- Report Header --}}
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight">{{ ucfirst($reportType) }} Performance Report</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $website?->domain ?? 'Unknown' }}</span>
                    &mdash;
                    @if ($startDate === $endDate)
                        {{ \Illuminate\Support\Carbon::parse($startDate)->format('l, F j, Y') }}
                    @else
                        {{ \Illuminate\Support\Carbon::parse($startDate)->format('M j') }} &ndash; {{ \Illuminate\Support\Carbon::parse($endDate)->format('M j, Y') }}
                    @endif
                </p>
            </div>
            <p class="text-xs italic text-slate-400 dark:text-slate-500">
                vs {{ $report['period']['previous_label'] }}
                ({{ \Illuminate\Support\Carbon::parse($report['period']['prev_start'])->format('M j') }} &ndash; {{ \Illuminate\Support\Carbon::parse($report['period']['prev_end'])->format('M j') }})
            </p>
        </div>
    </div>

    {{-- ==================== GOOGLE ANALYTICS ==================== --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-600/20 ring-inset dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30">Google Analytics</span>
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Website Traffic</h3>
            </div>
        </div>

        <div class="p-6">
            <div class="mb-6 grid grid-cols-3 gap-3">
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'Users',
                    'metric' => $report['analytics']['users'],
                    'format' => 'number',
                ])
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'Sessions',
                    'metric' => $report['analytics']['sessions'],
                    'format' => 'number',
                ])
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'Bounce Rate',
                    'metric' => $report['analytics']['bounce_rate'],
                    'format' => 'percent',
                    'changeSuffix' => 'pp',
                ])
            </div>

            {{-- Top Sources --}}
            @if (count($report['analytics']['top_sources']) > 0)
                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Top Traffic Sources</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-3">Source</th>
                                <th class="px-4 py-3 text-right">{{ $report['period']['current_label'] }}</th>
                                <th class="px-4 py-3 text-right">{{ $report['period']['previous_label'] }}</th>
                                <th class="px-4 py-3 text-right">Change</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($report['analytics']['top_sources'] as $source)
                                <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-300">{{ $source['source'] }}</td>
                                    <td class="px-4 py-3 text-right text-slate-900 dark:text-slate-100">{{ number_format($source['users']) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-400">{{ number_format($source['prev_users']) }}</td>
                                    <td class="px-4 py-3 text-right">@include('livewire.reports.partials.change-badge', ['metric' => $source['change']])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-400 dark:text-slate-500">No analytics data available for this period.</p>
            @endif
        </div>
    </div>

    {{-- ==================== GOOGLE SEARCH CONSOLE ==================== --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center rounded-md bg-purple-50 px-2.5 py-1 text-xs font-semibold text-purple-700 ring-1 ring-purple-600/20 ring-inset dark:bg-purple-500/10 dark:text-purple-400 dark:ring-purple-500/30">Google Search Console</span>
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Search Performance</h3>
            </div>
        </div>

        <div class="p-6">
            <div class="mb-6 grid grid-cols-4 gap-3">
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'Clicks',
                    'metric' => $report['search_console']['clicks'],
                    'format' => 'number',
                ])
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'Impressions',
                    'metric' => $report['search_console']['impressions'],
                    'format' => 'number',
                ])
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'Avg Position',
                    'metric' => $report['search_console']['position'],
                    'format' => 'decimal',
                ])
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'Avg CTR',
                    'metric' => $report['search_console']['ctr'],
                    'format' => 'percent',
                    'changeSuffix' => 'pp',
                ])
            </div>

            {{-- Top Queries --}}
            @if (count($report['search_console']['top_queries']) > 0)
                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Top Search Queries</h4>
                <div class="mb-6 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-3">Query</th>
                                <th class="px-4 py-3 text-right">Clicks</th>
                                <th class="px-4 py-3 text-right">Prev</th>
                                <th class="px-4 py-3 text-right">Impr.</th>
                                <th class="px-4 py-3 text-right">Pos</th>
                                <th class="px-4 py-3 text-right">CTR</th>
                                <th class="px-4 py-3 text-right">Change</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($report['search_console']['top_queries'] as $q)
                                <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="max-w-[200px] truncate px-4 py-3 font-medium text-slate-700 dark:text-slate-300">{{ $q['query'] }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ number_format($q['clicks']) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-400">{{ number_format($q['prev_clicks']) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($q['impressions']) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ $q['position'] }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ $q['ctr'] }}%</td>
                                    <td class="px-4 py-3 text-right">@include('livewire.reports.partials.change-badge', ['metric' => $q['change']])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Top Pages --}}
            @if (count($report['search_console']['top_pages']) > 0)
                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Top Pages</h4>
                <div class="mb-6 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-3">Page</th>
                                <th class="px-4 py-3 text-right">Clicks</th>
                                <th class="px-4 py-3 text-right">Prev</th>
                                <th class="px-4 py-3 text-right">Impr.</th>
                                <th class="px-4 py-3 text-right">CTR</th>
                                <th class="px-4 py-3 text-right">Change</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($report['search_console']['top_pages'] as $p)
                                <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="max-w-[250px] truncate px-4 py-3 font-medium text-slate-700 dark:text-slate-300" title="{{ $p['page'] }}">{{ \Illuminate\Support\Str::limit($p['page'], 55) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ number_format($p['clicks']) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-400">{{ number_format($p['prev_clicks']) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($p['impressions']) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ $p['ctr'] }}%</td>
                                    <td class="px-4 py-3 text-right">@include('livewire.reports.partials.change-badge', ['metric' => $p['change']])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (count($report['search_console']['devices']) > 0)
                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Device Breakdown</h4>
                <div class="mb-6 grid grid-cols-3 gap-3">
                    @foreach ($report['search_console']['devices'] as $device)
                        <div class="min-w-0 rounded-lg border border-slate-100 p-3 dark:border-slate-800">
                            <p class="truncate text-xs font-medium uppercase tracking-wider text-slate-400">{{ ucfirst($device['device']) }}</p>
                            <p class="mt-1 text-lg font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ number_format($device['clicks']) }}</p>
                            <p class="text-xs text-slate-400">{{ $device['percentage'] }}% of clicks</p>
                            <div class="mt-1">@include('livewire.reports.partials.change-badge', ['metric' => $device['change']])</div>
                            <p class="text-xs text-slate-400">was {{ number_format($device['prev_clicks']) }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Countries --}}
            @if (count($report['search_console']['countries']) > 0)
                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Top Countries</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-3">Country</th>
                                <th class="px-4 py-3 text-right">Clicks</th>
                                <th class="px-4 py-3 text-right">Prev</th>
                                <th class="px-4 py-3 text-right">Impr.</th>
                                <th class="px-4 py-3 text-right">Change</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($report['search_console']['countries'] as $c)
                                <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-300">{{ $c['country'] ?: 'Unknown' }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ number_format($c['clicks']) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-400">{{ number_format($c['prev_clicks']) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($c['impressions']) }}</td>
                                    <td class="px-4 py-3 text-right">@include('livewire.reports.partials.change-badge', ['metric' => $c['change']])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (count($report['search_console']['top_queries']) === 0 && count($report['search_console']['top_pages']) === 0)
                <p class="text-sm text-slate-400 dark:text-slate-500">No search console data available for this period.</p>
            @endif
        </div>
    </div>

    {{-- ==================== BACKLINKS ==================== --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 ring-inset dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30">Backlinks</span>
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Link Profile</h3>
            </div>
        </div>

        <div class="p-6">
            <div class="mb-6 grid grid-cols-4 gap-3">
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'New Backlinks',
                    'metric' => $report['backlinks']['count'],
                    'format' => 'number',
                ])
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'Avg DA',
                    'metric' => $report['backlinks']['avg_da'],
                    'format' => 'decimal',
                ])
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'Dofollow',
                    'metric' => $report['backlinks']['dofollow'],
                    'format' => 'number',
                ])
                @include('livewire.reports.partials.kpi-card', [
                    'label' => 'Nofollow',
                    'metric' => $report['backlinks']['nofollow'],
                    'format' => 'number',
                ])
            </div>

            {{-- Top Backlinks --}}
            @if (count($report['backlinks']['top_backlinks']) > 0)
                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Top Backlinks by Domain Authority</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-3">Referring Page</th>
                                <th class="px-4 py-3">Target</th>
                                <th class="px-4 py-3 text-right">DA</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Follow</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($report['backlinks']['top_backlinks'] as $b)
                                <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="max-w-[200px] truncate px-4 py-3">
                                        <a href="{{ $b['referring_page_url'] }}" target="_blank" class="text-indigo-600 hover:underline dark:text-indigo-400" title="{{ $b['referring_page_url'] }}">
                                            {{ \Illuminate\Support\Str::limit($b['referring_page_url'], 45) }}
                                        </a>
                                    </td>
                                    <td class="max-w-[180px] truncate px-4 py-3 text-slate-600 dark:text-slate-300" title="{{ $b['target_page_url'] }}">
                                        {{ \Illuminate\Support\Str::limit($b['target_page_url'], 40) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ $b['domain_authority'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $b['type'] }}</td>
                                    <td class="px-4 py-3">
                                        @if ($b['is_dofollow'])
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20 ring-inset dark:bg-emerald-500/10 dark:text-emerald-400">Do</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-slate-500/10 ring-inset dark:bg-slate-800 dark:text-slate-400">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-400 dark:text-slate-500">No backlinks recorded for this period.</p>
            @endif
        </div>
    </div>
</div>
