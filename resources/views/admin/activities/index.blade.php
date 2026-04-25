<x-layouts.app>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Admin Activities</h1>
            <p class="text-sm text-slate-500">Client activity feed including API usage.</p>
        </div>

        <form method="GET" class="grid gap-2 md:grid-cols-4">
            <select name="user_id" class="rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="0">All users</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected($filters['userId'] === $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
            <input type="text" name="type" value="{{ $filters['type'] }}" placeholder="Activity type" class="rounded border border-slate-300 px-3 py-2 text-sm" />
            <input type="text" name="provider" value="{{ $filters['provider'] }}" placeholder="Provider (serp_api etc.)" class="rounded border border-slate-300 px-3 py-2 text-sm" />
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>

        <div class="overflow-auto rounded border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-3 py-2">Time</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Client</th>
                        <th class="px-3 py-2">Actor</th>
                        <th class="px-3 py-2">Website</th>
                        <th class="px-3 py-2">Provider</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activities as $a)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $a->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $a->type }}</td>
                            <td class="px-3 py-2">{{ $a->user?->email ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $a->actor?->email ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $a->website?->domain ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $a->provider ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $activities->links() }}
    </div>
</x-layouts.app>
