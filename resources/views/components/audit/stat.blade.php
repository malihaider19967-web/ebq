@props([
    'label' => '',
    'value' => '',
    'tone' => 'neutral',
])
@php
    $valueClass = match ($tone) {
        'good' => 'text-emerald-600 dark:text-emerald-400',
        'warn' => 'text-amber-600 dark:text-amber-400',
        'bad' => 'text-rose-600 dark:text-rose-400',
        default => 'text-slate-800 dark:text-slate-100',
    };
    $dotClass = match ($tone) {
        'good' => 'bg-emerald-500',
        'warn' => 'bg-amber-500',
        'bad' => 'bg-rose-500',
        default => 'bg-slate-300 dark:bg-slate-600',
    };
@endphp
<div class="rounded-lg border border-slate-200 bg-white p-3 transition hover:shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="flex items-center justify-between">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $label }}</p>
        <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}"></span>
    </div>
    <p class="mt-1.5 text-xl font-bold tabular-nums leading-none {{ $valueClass }}">{{ $value }}</p>
</div>
