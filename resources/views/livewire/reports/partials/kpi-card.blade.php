@php
    $format = $format ?? 'number';
    $changeSuffix = $changeSuffix ?? '%';
    $dir = $metric['direction'];
    $pct = $metric['change_percent'];
    $isPos = $metric['is_positive'];

    $formattedValue = match ($format) {
        'percent' => $metric['current'] . '%',
        'decimal' => $metric['current'],
        default => number_format($metric['current']),
    };

    $formattedPrev = match ($format) {
        'percent' => $metric['previous'] . '%',
        'decimal' => $metric['previous'],
        default => number_format($metric['previous']),
    };

    $changeColor = match (true) {
        $dir === 'flat' => 'text-slate-400',
        $isPos => 'text-emerald-600 dark:text-emerald-400',
        default => 'text-red-600 dark:text-red-400',
    };

    $changeBg = match (true) {
        $dir === 'flat' => 'bg-slate-50 dark:bg-slate-800',
        $isPos => 'bg-emerald-50 dark:bg-emerald-500/10',
        default => 'bg-red-50 dark:bg-red-500/10',
    };

    $arrow = match ($dir) {
        'up' => '↑',
        'down' => '↓',
        default => '→',
    };

    if ($dir === 'flat') {
        $changeText = 'No change';
    } elseif ($pct !== null) {
        $changeText = ($dir === 'up' ? '+' : '') . $pct . $changeSuffix;
    } else {
        $changeText = 'New';
    }
@endphp

<div class="min-w-0 rounded-lg border border-slate-100 p-3 sm:p-4 dark:border-slate-800">
    <p class="truncate text-xs font-medium uppercase tracking-wider text-slate-400">{{ $label }}</p>
    <p class="mt-1 truncate text-lg font-bold tabular-nums text-slate-900 sm:text-2xl dark:text-slate-100">{{ $formattedValue }}</p>
    <div class="mt-2 flex items-center gap-2">
        <span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $changeBg }} {{ $changeColor }}">
            {{ $arrow }} {{ $changeText }}
        </span>
    </div>
    <p class="mt-1 text-xs text-slate-400">was {{ $formattedPrev }}</p>
</div>
