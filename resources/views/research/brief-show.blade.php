<x-layouts.app>
    @php
        $brief = \App\Models\Research\ContentBrief::query()->with(['keyword', 'website'])->findOrFail($briefId);
        abort_unless(auth()->user()?->canViewWebsiteId($brief->website_id), 403);
    @endphp
    <div class="space-y-6">
        <div>
            <a href="{{ route('research.briefs') }}" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">&larr; Back to briefs</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $brief->keyword?->query ?? 'Brief' }}</h1>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Created {{ $brief->created_at?->diffForHumans() }}</p>
        </div>
        <pre class="overflow-x-auto rounded-md border border-slate-200 bg-slate-50 p-4 text-xs dark:border-slate-800 dark:bg-slate-900">{{ json_encode($brief->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</x-layouts.app>
