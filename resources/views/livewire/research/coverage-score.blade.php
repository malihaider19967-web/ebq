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

    @if ($report === null)
        <p class="text-xs text-slate-400">Pick a page to compute its coverage score.</p>
    @else
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold">{{ $report['title'] ?: $report['url'] }}</div>
                    <a href="{{ $report['url'] }}" target="_blank" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">{{ $report['url'] }}</a>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold tabular-nums">{{ (int) ($report['score'] * 100) }}</div>
                    <div class="text-[10px] uppercase text-slate-500">/100</div>
                </div>
            </div>
            <dl class="mt-4 grid grid-cols-3 gap-3 text-xs">
                <div>
                    <dt class="text-slate-500">Words</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format($report['word_count'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Headings</dt>
                    <dd class="font-semibold tabular-nums">{{ $report['heading_count'] }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Keywords ranked</dt>
                    <dd class="font-semibold tabular-nums">{{ $report['keyword_count'] }}</dd>
                </div>
            </dl>
        </div>
    @endif
</div>
