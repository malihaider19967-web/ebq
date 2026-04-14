<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
        ['label' => 'Total Clicks', 'value' => number_format($data['clicks']), 'color' => 'text-blue-600 dark:text-blue-400', 'bg' => 'bg-blue-50 dark:bg-blue-900/20'],
        ['label' => 'Impressions', 'value' => number_format($data['impressions']), 'color' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-900/20'],
        ['label' => 'Users', 'value' => number_format($data['users']), 'color' => 'text-violet-600 dark:text-violet-400', 'bg' => 'bg-violet-50 dark:bg-violet-900/20'],
        ['label' => 'Sessions', 'value' => number_format($data['sessions']), 'color' => 'text-amber-600 dark:text-amber-400', 'bg' => 'bg-amber-50 dark:bg-amber-900/20'],
    ] as $card)
        <div class="rounded-lg bg-white p-5 shadow dark:bg-slate-900">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
            <p class="mt-2 text-2xl font-bold {{ $card['color'] }}">{{ $card['value'] }}</p>
        </div>
    @endforeach
</div>
