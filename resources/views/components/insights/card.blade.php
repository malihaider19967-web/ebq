@props(['title', 'description' => null])
<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <h3 class="text-sm font-semibold">{{ $title }}</h3>
    </div>
    @if ($description)
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif
    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
