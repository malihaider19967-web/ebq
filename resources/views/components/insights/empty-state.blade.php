@props(['title', 'body' => null])
<div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-4 py-10 text-center dark:border-slate-800 dark:bg-slate-900/40">
    <svg class="h-8 w-8 text-slate-300 dark:text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
    </svg>
    <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">{{ $title }}</p>
    @if ($body)
        <p class="mt-1 max-w-md text-xs text-slate-500 dark:text-slate-400">{{ $body }}</p>
    @endif
</div>
