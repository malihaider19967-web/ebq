<x-layouts.app>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Competitor scans</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Admin-triggered Python crawls. Each scan walks a competitor domain (with capped external follow), extracts keywords + topics, writes back to MySQL.</p>
            </div>
            <a href="{{ route('admin.research.competitor-scans.create') }}" class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">New scan</a>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">{{ session('status') }}</div>
        @endif

        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Domain</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Status</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Pages</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Triggered by</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">When</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                    @forelse ($scans as $scan)
                        @php
                            $statusClasses = match ($scan->status) {
                                'running' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200',
                                'queued' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                'cancelling' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200',
                                'done' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200',
                                'failed' => 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $scan->seed_domain }}</td>
                            <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $statusClasses }}">{{ $scan->status }}</span></td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($scan->page_count) }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $scan->triggeredBy?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $scan->created_at?->diffForHumans() }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('admin.research.competitor-scans.show', $scan) }}" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-xs text-slate-400">No competitor scans yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $scans->links() }}</div>
    </div>
</x-layouts.app>
