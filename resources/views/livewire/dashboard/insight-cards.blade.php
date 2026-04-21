<div>
    <div wire:loading.flex wire:target="switchWebsite" class="mb-2 items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400" role="status" aria-live="polite">
        <svg class="h-3 w-3 animate-spin text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" class="opacity-75"></path></svg>
        Refreshing insights…
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $cards = [
                ['key' => 'cannibalizations', 'label' => 'Cannibalizations', 'tone' => 'amber', 'tab' => 'cannibalization', 'hint' => 'Queries split across pages', 'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z'],
                ['key' => 'striking_distance', 'label' => 'Striking distance', 'tone' => 'indigo', 'tab' => 'striking_distance', 'hint' => 'Pos 5–20 with low CTR', 'icon' => 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z'],
                ['key' => 'indexing_fails_with_traffic', 'label' => 'Index fails w/ traffic', 'tone' => 'red', 'tab' => 'indexing_fails', 'hint' => 'Not-PASS pages earning impressions', 'icon' => 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z'],
                ['key' => 'content_decay', 'label' => 'Content decay', 'tone' => 'slate', 'tab' => 'content_decay', 'hint' => 'Pages losing clicks 28d/28d', 'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625z'],
            ];
        @endphp
        @foreach ($cards as $card)
            @php($count = $counts[$card['key']] ?? 0)
            <a href="{{ route('reports.index') }}?insight={{ $card['tab'] }}"
                wire:loading.class.delay="opacity-60"
                aria-label="{{ $card['label'] }}: {{ $count }}. {{ $card['hint'] }}. Opens Insights in Reports."
                class="group flex min-h-[142px] flex-col rounded-xl border border-slate-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-500/40">
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
                        ]) xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" /></svg>
                    </span>
                </div>
                <p @class([
                    'mt-3 text-3xl font-bold tracking-tight tabular-nums',
                    'text-amber-600 dark:text-amber-400' => $card['tone'] === 'amber' && $count > 0,
                    'text-indigo-600 dark:text-indigo-400' => $card['tone'] === 'indigo' && $count > 0,
                    'text-red-600 dark:text-red-400' => $card['tone'] === 'red' && $count > 0,
                    'text-slate-700 dark:text-slate-200' => $card['tone'] === 'slate' || $count === 0,
                ])>{{ number_format($count) }}</p>
                <p class="mt-auto pt-2 text-xs text-slate-400 dark:text-slate-500 group-hover:text-slate-500 dark:group-hover:text-slate-400">
                    {{ $card['hint'] }}
                    <span aria-hidden="true" class="inline-block transition-transform group-hover:translate-x-0.5">→</span>
                </p>
            </a>
        @endforeach
    </div>
</div>
