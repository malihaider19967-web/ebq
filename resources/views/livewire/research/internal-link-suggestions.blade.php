<div>
    <div class="mb-4 max-w-xl">
        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Page</label>
        <select wire:model.live="pageId" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Select a page…</option>
            @foreach ($pages as $page)
                <option value="{{ $page->id }}" @selected($pageId === $page->id)>{{ $page->title ?: $page->url }}</option>
            @endforeach
        </select>
    </div>

    @if ($pageId === null)
        <p class="text-xs text-slate-400">Pick a page to see internal-link candidates.</p>
    @else
        <div class="space-y-2">
            @forelse ($links as $link)
                <div class="flex items-center justify-between rounded-md border border-slate-200 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-950">
                    <div class="flex-1">
                        <div class="text-sm">
                            <span class="text-slate-500">{{ $link->fromPage?->title ?: $link->fromPage?->url }}</span>
                            <span class="mx-2 text-slate-400">→</span>
                            <span class="font-medium">{{ $link->toPage?->title ?: $link->toPage?->url }}</span>
                        </div>
                        @if ($link->anchor_text)
                            <div class="text-xs text-slate-400">Anchor: "{{ $link->anchor_text }}"</div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $link->status }}</span>
                        @if ($link->status === 'suggested' || $link->status === 'discovered')
                            <button wire:click="accept({{ $link->id }})" class="rounded-md bg-emerald-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700">Accept</button>
                            <button wire:click="reject({{ $link->id }})" class="rounded-md border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">Reject</button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-xs text-slate-400">No internal-link suggestions yet for this page.</p>
            @endforelse
        </div>
    @endif
</div>
