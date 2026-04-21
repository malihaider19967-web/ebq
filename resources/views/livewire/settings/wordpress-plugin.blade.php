<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold">WordPress plugin</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Install the EBQ SEO plugin on your WordPress site and click <span class="font-semibold">Connect to EBQ</span> — you'll be bounced here to approve. No codes or tokens to copy.
            </p>
        </div>
        <a href="{{ asset('downloads/ebq-seo.zip') }}" download
            class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
            Download plugin
        </a>
    </div>

    @if ($statusMessage)
        <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    @if ($tokens->isEmpty())
        <div class="mt-5 flex flex-col items-start gap-2 rounded-lg border border-dashed border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Not connected yet</p>
            <ol class="list-inside list-decimal space-y-1 text-xs text-slate-600 dark:text-slate-300">
                <li>Download the plugin and upload it via <code class="rounded bg-white px-1 py-0.5 font-mono text-[10px] text-slate-700 dark:bg-slate-900 dark:text-slate-200">Plugins → Add New → Upload</code>.</li>
                <li>Activate it, then open <code class="rounded bg-white px-1 py-0.5 font-mono text-[10px] text-slate-700 dark:bg-slate-900 dark:text-slate-200">Settings → EBQ SEO</code>.</li>
                <li>Click <strong>Connect to EBQ</strong>. You'll bounce here to approve; the token is exchanged automatically.</li>
            </ol>
        </div>
    @else
        <div class="mt-5">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Active WordPress connections</p>
            <ul class="mt-2 divide-y divide-slate-100 rounded-md border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                @foreach ($tokens as $token)
                    <li class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800 dark:text-slate-100">{{ $token->name }}</p>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Connected {{ $token->created_at?->diffForHumans() }} · Last used {{ $token->last_used_at?->diffForHumans() ?? 'never' }}</p>
                        </div>
                        <button type="button" wire:click="revokeToken({{ $token->id }})"
                            wire:confirm="Revoke this connection? The plugin on that site will immediately lose access."
                            class="inline-flex h-7 items-center rounded-md border border-red-200 bg-white px-2.5 text-[11px] font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-800 dark:bg-slate-900 dark:text-red-400 dark:hover:bg-red-900/20">Revoke</button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
