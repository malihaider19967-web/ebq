<x-layouts.app>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Leads</h1>
                <p class="text-sm text-slate-500">Names &amp; emails captured from free landing-page audits.</p>
            </div>
            <div class="flex gap-2 text-sm">
                <span class="rounded-lg bg-slate-100 px-3 py-1.5 font-semibold text-slate-700">{{ number_format($totalCount) }} total</span>
                <span class="rounded-lg bg-emerald-100 px-3 py-1.5 font-semibold text-emerald-700">{{ number_format($convertedCount) }} converted</span>
            </div>
        </div>

        <form method="GET" class="grid gap-2 sm:grid-cols-4">
            <input type="text" name="q" value="{{ $filters['search'] }}" placeholder="Search name or email" class="rounded border border-slate-300 px-3 py-2 text-sm sm:col-span-2" />
            <select name="status" class="rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">All leads</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Not converted</option>
                <option value="converted" @selected($filters['status'] === 'converted')>Converted</option>
            </select>
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>

        <div class="overflow-auto rounded border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-3 py-2">Captured</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Email</th>
                        <th class="px-3 py-2">Audited URL</th>
                        <th class="px-3 py-2">Keyword</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leads as $lead)
                        <tr class="border-t border-slate-100 align-top">
                            <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ $lead->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 font-medium text-slate-800">{{ $lead->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $lead->email }}</td>
                            <td class="max-w-xs truncate px-3 py-2 text-slate-600" title="{{ $lead->guestPageAudit?->url }}">{{ $lead->guestPageAudit?->url ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $lead->guestPageAudit?->keyword ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @if ($lead->isConverted())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                        Converted{{ $lead->converted_at ? ' · '.$lead->converted_at->format('M j') : '' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Lead</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">No leads yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $leads->links() }}</div>
    </div>
</x-layouts.app>
