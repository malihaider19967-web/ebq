<x-layouts.app>
    <div class="space-y-6">
        <div>
            <a href="{{ route('admin.research.competitor-scans.index') }}" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">&larr; Back to scans</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">New competitor scan</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pick a seed URL, optional seed keywords, and the caps. Robots.txt is always respected; a polite 1s delay is built in.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.research.competitor-scans.store') }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
            @csrf

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Seed URL</label>
                <input type="url" name="seed_url" value="{{ old('seed_url') }}" placeholder="https://competitor.com" required
                    class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Attach to website (optional)</label>
                <select name="website_id" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <option value="">— Not associated —</option>
                    @foreach ($websites as $website)
                        <option value="{{ $website->id }}" @selected(old('website_id') == $website->id)>{{ $website->domain }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-500">Linking to a website surfaces the scan's pages on /research/competitors for that website's owner.</p>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Seed keywords (one per line, optional)</label>
                <textarea name="seed_keywords" rows="5" placeholder="best running shoes&#10;trail running gear" class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">{{ old('seed_keywords') }}</textarea>
                <p class="mt-1 text-[11px] text-slate-500">Used three ways: bias the crawl frontier toward seed-relevant pages, count occurrences per page, rank the competitor's best pages per keyword.</p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Max total pages</label>
                    <input type="number" name="max_total_pages" value="{{ old('max_total_pages', $defaults['max_total_pages']) }}" min="10" max="{{ $ceilings['max_total_pages'] }}" required
                        class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                    <p class="mt-1 text-[11px] text-slate-500">Server ceiling: {{ number_format($ceilings['max_total_pages']) }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Max depth</label>
                    <input type="number" name="max_depth" value="{{ old('max_depth', $defaults['max_depth']) }}" min="1" max="{{ $ceilings['max_depth'] }}" required
                        class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800" />
                </div>
            </div>
            <p class="text-[11px] text-slate-500">Crawl is bounded to the seed domain only. External links are recorded as outlinks; their domains become future research_targets after this scan completes.</p>

            <div class="pt-2">
                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">Queue scan</button>
            </div>
        </form>
    </div>
</x-layouts.app>
