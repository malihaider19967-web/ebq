<div wire:poll.5s class="space-y-6">
    @if ($flash !== '')
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">{{ $flash }}</div>
    @endif

    @if ($enginePaused || $autoDiscoveryDisabled)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
            @if ($enginePaused)
                <strong>Engine is paused.</strong> Scheduler tick is a no-op. Manual "Run now" actions also blocked.
            @elseif ($autoDiscoveryDisabled)
                <strong>Auto Serper discovery is disabled.</strong> Existing queued targets continue to be scraped on schedule; no new auto-discovered competitors are added.
            @endif
            <a href="{{ route('admin.research.settings.show') }}" class="ml-2 text-amber-900 underline dark:text-amber-100">Manage settings</a>
        </div>
    @endif

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
        @php
            $tileCards = [
                ['label' => 'Queued', 'value' => $tiles['queued'], 'tone' => 'slate'],
                ['label' => 'Scanning', 'value' => $tiles['scanning'], 'tone' => 'indigo'],
                ['label' => 'Paused', 'value' => $tiles['paused'], 'tone' => 'amber'],
                ['label' => 'Done (total)', 'value' => $tiles['done_total'], 'tone' => 'emerald'],
                ['label' => 'Scans today', 'value' => $tiles['scans_today'], 'tone' => 'slate'],
                ['label' => 'Pages today', 'value' => $tiles['pages_today'], 'tone' => 'slate'],
                ['label' => 'Links today', 'value' => $tiles['links_today'], 'tone' => 'slate'],
            ];
        @endphp
        @foreach ($tileCards as $tile)
            <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950">
                <div class="text-[10px] uppercase tracking-wider text-slate-500">{{ $tile['label'] }}</div>
                <div class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($tile['value']) }}</div>
            </div>
        @endforeach
    </div>

    {{-- In-flight scans --}}
    @if ($running->count() > 0)
        <div class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
            <div class="border-b border-slate-200 px-4 py-2 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800">In flight</div>
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Domain</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Status</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Pages</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Started</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Heartbeat</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    @foreach ($running as $scan)
                        @php
                            $maxPages = $scan->caps['max_total_pages'] ?? null;
                            $progress = $maxPages ? min(1.0, $scan->page_count / max(1, $maxPages)) : 0.0;
                            $statusClass = match ($scan->status) {
                                'queued' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                'running' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200',
                                'cancelling' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $scan->seed_domain }}</td>
                            <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $statusClass }}">{{ $scan->status }}</span></td>
                            <td class="px-3 py-2 text-right">
                                <div class="tabular-nums">{{ number_format($scan->page_count) }}@if ($maxPages) <span class="text-slate-400">/ {{ number_format($maxPages) }}</span>@endif</div>
                                @if ($maxPages)
                                    <div class="mt-1 h-1 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div class="h-full bg-indigo-500" style="width: {{ (int) ($progress * 100) }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $scan->started_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $scan->last_heartbeat_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('admin.research.competitor-scans.show', $scan) }}" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Queue + filters --}}
    <div class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-2 dark:border-slate-800">
            <div class="text-xs font-semibold uppercase text-slate-500">Research targets queue</div>
            <div class="flex flex-wrap items-center gap-2">
                <input wire:model.live.debounce.400ms="search" type="text" placeholder="Filter by domain…" class="h-8 w-44 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                <select wire:model.live="statusFilter" class="h-8 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">All statuses</option>
                    <option value="queued">Queued</option>
                    <option value="scanning">Scanning</option>
                    <option value="done">Done</option>
                    <option value="paused">Paused</option>
                    <option value="blacklisted">Blacklisted</option>
                </select>
                <select wire:model.live="sourceFilter" class="h-8 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">All sources</option>
                    <option value="manual">Manual</option>
                    <option value="website-onboarding">Website onboarding</option>
                    <option value="serp-competitor">SERP competitor</option>
                    <option value="outlink">Outlink</option>
                    <option value="user-supplied">User-supplied</option>
                </select>
            </div>
        </div>
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Domain</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Source</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Priority</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Status</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Scans</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Last scanned</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">For website</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                @forelse ($queue as $target)
                    @php
                        $statusClass = match ($target->status) {
                            'queued' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                            'scanning' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200',
                            'done' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200',
                            'paused' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200',
                            'blacklisted' => 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-200',
                            default => 'bg-slate-100 text-slate-700',
                        };
                    @endphp
                    <tr>
                        <td class="px-3 py-2 font-medium">
                            {{ $target->domain }}
                            @if ($target->root_url)
                                <a href="{{ $target->root_url }}" target="_blank" rel="noopener" class="ml-1 text-[11px] text-indigo-600 hover:underline dark:text-indigo-400">visit</a>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ $target->source }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $target->priority }}</td>
                        <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $statusClass }}">{{ $target->status }}</span></td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $target->total_scans }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ $target->last_scanned_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ $target->attachedWebsite?->domain ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @if (in_array($target->status, ['queued', 'paused'], true))
                                    <button wire:click="runNow({{ $target->id }})" class="rounded-md bg-indigo-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-indigo-700">Run now</button>
                                @endif
                                @if ($target->status === 'queued')
                                    <button wire:click="pauseTarget({{ $target->id }})" class="rounded-md border border-amber-200 px-2 py-1 text-[11px] font-semibold text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300">Pause</button>
                                @elseif ($target->status === 'paused')
                                    <button wire:click="resumeTarget({{ $target->id }})" class="rounded-md border border-emerald-200 px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300">Resume</button>
                                @endif
                                @if ($target->last_scan_id)
                                    <a href="{{ route('admin.research.competitor-scans.show', $target->last_scan_id) }}" class="text-[11px] text-indigo-600 hover:underline dark:text-indigo-400">Last scan</a>
                                @endif
                                @if ($target->status !== 'scanning')
                                    <button wire:click="deleteTarget({{ $target->id }})" wire:confirm="Remove {{ $target->domain }} from the queue?" class="rounded-md border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">Remove</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-8 text-center text-xs text-slate-400">No targets match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recently completed scans --}}
    <div class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
        <div class="border-b border-slate-200 px-4 py-2 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800">Recently completed scans</div>
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Domain</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Status</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Pages</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Finished</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                @forelse ($recentScans as $scan)
                    @php
                        $rowClass = $scan->status === 'failed'
                            ? 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-200'
                            : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200';
                    @endphp
                    <tr>
                        <td class="px-3 py-2 font-medium">{{ $scan->seed_domain }}</td>
                        <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $rowClass }}">{{ $scan->status }}</span></td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($scan->page_count) }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ $scan->finished_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('admin.research.competitor-scans.show', $scan) }}" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-8 text-center text-xs text-slate-400">No completed scans yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
