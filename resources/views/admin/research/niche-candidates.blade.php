<x-layouts.app>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Niche candidates</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">DiscoverEmergingNichesJob proposes these. Approve them into a parent vertical or reject and delete.</p>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">{{ session('status') }}</div>
        @endif

        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Slug</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Name</th>
                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Found</th>
                        <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                    @forelse ($candidates as $candidate)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs">{{ $candidate->slug }}</td>
                            <td class="px-3 py-2">
                                <form method="POST" action="{{ route('admin.research.niche-candidates.approve', $candidate) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="text" name="name" value="{{ $candidate->name }}" class="h-8 w-48 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                                    <select name="parent_id" class="h-8 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                        <option value="">No parent</option>
                                        @foreach ($parents as $parent)
                                            <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="rounded-md bg-emerald-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700">Approve</button>
                                </form>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $candidate->created_at?->diffForHumans() }}</td>
                            <td class="px-3 py-2 text-right">
                                <form method="POST" action="{{ route('admin.research.niche-candidates.destroy', $candidate) }}" onsubmit="return confirm('Reject and delete this candidate?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-rose-200 px-2 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-900/30">Reject</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-xs text-slate-400">No pending candidates. The job runs weekly.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $candidates->links() }}</div>
    </div>
</x-layouts.app>
