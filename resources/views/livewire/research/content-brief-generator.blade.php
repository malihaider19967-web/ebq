<div>
    <form wire:submit.prevent="create" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Keyword</label>
            <input wire:model="seedKeyword" type="text" placeholder="best running shoes"
                class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
        </div>
        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">New brief</button>
    </form>

    @if ($status)
        <div class="mb-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-800/60">{{ $status }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Keyword</th>
                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase text-slate-500">Created</th>
                    <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase text-slate-500"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                @forelse ($briefs as $brief)
                    <tr>
                        <td class="px-3 py-2">{{ $brief->keyword?->query ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs text-slate-500">{{ $brief->created_at?->diffForHumans() }}</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('research.briefs.show', $brief->id) }}" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-8 text-center text-xs text-slate-400">No briefs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
