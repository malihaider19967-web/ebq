<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        ['key' => 'cannibalizations', 'label' => 'Cannibalizations', 'tone' => 'amber', 'tab' => 'cannibalization', 'hint' => 'Queries split across pages'],
        ['key' => 'striking_distance', 'label' => 'Striking distance', 'tone' => 'indigo', 'tab' => 'striking_distance', 'hint' => 'Pos 5–20 with low CTR'],
        ['key' => 'indexing_fails_with_traffic', 'label' => 'Index fails w/ traffic', 'tone' => 'red', 'tab' => 'indexing_fails', 'hint' => 'Not-PASS pages earning impressions'],
        ['key' => 'content_decay', 'label' => 'Content decay', 'tone' => 'slate', 'tab' => 'content_decay', 'hint' => 'Pages losing clicks 28d/28d'],
    ] as $card)
        @php($count = $counts[$card['key']] ?? 0)
        <a href="{{ route('reports.index') }}?insight={{ $card['tab'] }}"
            class="group flex min-h-[142px] flex-col rounded-xl border border-slate-200 bg-white p-5 transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                <span @class([
                    'flex h-9 w-9 items-center justify-center rounded-lg',
                    'bg-amber-100 dark:bg-amber-500/20' => $card['tone'] === 'amber',
                    'bg-indigo-100 dark:bg-indigo-500/20' => $card['tone'] === 'indigo',
                    'bg-red-100 dark:bg-red-500/20' => $card['tone'] === 'red',
                    'bg-slate-100 dark:bg-slate-500/20' => $card['tone'] === 'slate',
                ])>
                    <svg @class([
                        'h-4 w-4',
                        'text-amber-600 dark:text-amber-400' => $card['tone'] === 'amber',
                        'text-indigo-600 dark:text-indigo-400' => $card['tone'] === 'indigo',
                        'text-red-600 dark:text-red-400' => $card['tone'] === 'red',
                        'text-slate-600 dark:text-slate-300' => $card['tone'] === 'slate',
                    ]) xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                </span>
            </div>
            <p @class([
                'mt-3 text-3xl font-bold tracking-tight tabular-nums',
                'text-amber-600 dark:text-amber-400' => $card['tone'] === 'amber',
                'text-indigo-600 dark:text-indigo-400' => $card['tone'] === 'indigo',
                'text-red-600 dark:text-red-400' => $card['tone'] === 'red',
                'text-slate-700 dark:text-slate-200' => $card['tone'] === 'slate',
            ])>{{ number_format($count) }}</p>
            <p class="mt-auto pt-2 text-xs text-slate-400 dark:text-slate-500 group-hover:text-slate-500 dark:group-hover:text-slate-400">{{ $card['hint'] }} →</p>
        </a>
    @endforeach
</div>
