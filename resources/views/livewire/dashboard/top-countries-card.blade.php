<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('Top countries') }}
            </p>
            <p class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">
                {{ __('Last 30 days vs previous 30 days, by clicks') }}
            </p>
        </div>
    </div>

    @if (empty($rows))
        <p class="mt-6 text-xs text-slate-500 dark:text-slate-400">
            {{ __('No country data yet. Run `php artisan ebq:resync-gsc --days=30` on the server to backfill.') }}
        </p>
    @else
        <ul class="mt-5 space-y-2.5">
            @foreach ($rows as $row)
                <li class="grid grid-cols-12 items-center gap-2">
                    <span class="col-span-3 truncate text-xs font-medium text-slate-700 dark:text-slate-200">
                        {{ $row['flag'] ? $row['flag'].' ' : '' }}{{ $row['name'] }}
                    </span>
                    <div class="col-span-6 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ $row['width_pct'] }}%"></div>
                    </div>
                    <span class="col-span-2 text-right text-xs font-semibold tabular-nums text-slate-800 dark:text-slate-100">
                        {{ number_format($row['clicks']) }}
                    </span>
                    <span class="col-span-1 text-right">
                        @php
                            $dir = $row['change']['direction'] ?? 'flat';
                            $pct = $row['change']['change_percent'] ?? null;
                        @endphp
                        @if ($dir === 'up')
                            <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">+{{ $pct }}%</span>
                        @elseif ($dir === 'down')
                            <span class="text-[10px] font-semibold text-red-600 dark:text-red-400">{{ $pct }}%</span>
                        @else
                            <span class="text-[10px] text-slate-400">—</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
