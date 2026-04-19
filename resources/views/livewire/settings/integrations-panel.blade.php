<div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="border-b border-slate-200 px-5 py-3.5 dark:border-slate-800">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Google Account</h2>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Connect your Google account for Analytics and Search Console data.</p>
    </div>
    <div class="px-5 py-4">
        @if ($googleAccount)
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-500/10">
                        <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">Connected</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400" title="{{ $googleAccount->expires_at ? format_user_datetime($googleAccount->expires_at) : '' }}">Expires {{ $googleAccount->expires_at?->diffForHumans() ?? 'unknown' }}</p>
                    </div>
                </div>
                <a href="{{ route('google.redirect') }}" class="inline-flex h-8 items-center rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Reconnect</a>
            </div>
        @else
            <a href="{{ route('google.redirect') }}" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-indigo-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#fff"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#fff" fill-opacity=".7"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#fff" fill-opacity=".5"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#fff" fill-opacity=".8"/></svg>
                Connect Google Account
            </a>
        @endif
    </div>
</div>
