<x-layouts.app>
    <x-admin.plugin-tabs current="adoption" />

    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold">Plugin Adoption</h2>
            <p class="text-sm text-slate-500">Track plugin connections and installed versions per client website.</p>
        </div>

        <form method="GET" class="flex gap-2">
            <input type="text" name="domain" value="{{ $domain }}" placeholder="Filter by domain" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>

        <div class="overflow-auto rounded border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-3 py-2">Domain</th>
                        <th class="px-3 py-2">Owner</th>
                        <th class="px-3 py-2">Connections</th>
                        <th class="px-3 py-2">Last Token Use</th>
                        <th class="px-3 py-2">Installed</th>
                        <th class="px-3 py-2">Channel</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $website)
                        @php $tok = $tokenCounts[$website->id] ?? null; @endphp
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $website->domain }}</td>
                            <td class="px-3 py-2">{{ $website->owner?->email ?? '-' }}</td>
                            <td class="px-3 py-2">{{ (int) ($tok->total ?? 0) }}</td>
                            <td class="px-3 py-2">{{ $tok && $tok->last_used_at ? \Illuminate\Support\Carbon::parse($tok->last_used_at)->diffForHumans() : 'never' }}</td>
                            <td class="px-3 py-2">{{ $website->pluginInstall?->installed_version ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $website->pluginInstall?->channel ?? 'stable' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $items->links() }}
    </div>
</x-layouts.app>
