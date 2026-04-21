<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold">WordPress plugin</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Connect this website to the EBQ WordPress plugin so editors see insights inside Gutenberg.</p>
        </div>
        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
            @if ($tokens instanceof \Illuminate\Support\Collection && $tokens->isNotEmpty())
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Connected
            @else
                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Not connected
            @endif
        </span>
    </div>

    @if ($statusError)
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400" role="alert">{{ $statusError }}</div>
    @endif
    @if ($statusSuccess)
        <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400" role="status">{{ $statusSuccess }}</div>
    @endif

    {{-- Step 1: challenge --}}
    <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Step 1 — Generate challenge</p>
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">EBQ issues a one-time code. Paste it into the WP plugin's settings page so it serves the code at <code class="rounded bg-white px-1 py-0.5 font-mono text-[10px] text-slate-700 dark:bg-slate-900 dark:text-slate-200">/.well-known/ebq-verification.txt</code>.</p>

        @if ($challengeCode)
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <code class="flex-1 overflow-x-auto rounded-md border border-slate-200 bg-white px-3 py-2 font-mono text-[11px] text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">{{ $challengeCode }}</code>
                <button type="button" onclick="navigator.clipboard.writeText('{{ $challengeCode }}')"
                    class="inline-flex h-8 items-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Copy</button>
            </div>
            <p class="mt-1 text-[10px] text-slate-400">Expires at {{ $challengeExpiresAt }}</p>
        @else
            <button type="button" wire:click="generateChallenge"
                class="mt-3 inline-flex h-8 items-center rounded-md bg-indigo-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-500">Generate challenge</button>
        @endif
    </div>

    {{-- Step 2: verify --}}
    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Step 2 — Verify + mint token</p>
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Once the plugin is active and serving the challenge file, click Verify. EBQ will fetch the file, confirm the code, and mint an API token shown below.</p>
        <button type="button" wire:click="verify" wire:loading.attr="disabled"
            class="mt-3 inline-flex h-8 items-center gap-1.5 rounded-md border border-indigo-300 bg-white px-3 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50 disabled:opacity-60 dark:border-indigo-500/40 dark:bg-slate-900 dark:text-indigo-300 dark:hover:bg-indigo-500/10">
            <span wire:loading.remove wire:target="verify">Verify now</span>
            <span wire:loading wire:target="verify">Verifying…</span>
        </button>
    </div>

    @if ($plainToken)
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-700/60 dark:bg-emerald-900/20">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Your API token (shown once)</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <code class="flex-1 overflow-x-auto rounded-md border border-emerald-300 bg-white px-3 py-2 font-mono text-[11px] text-emerald-800 dark:border-emerald-700/60 dark:bg-slate-900 dark:text-emerald-200">{{ $plainToken }}</code>
                <button type="button" onclick="navigator.clipboard.writeText('{{ $plainToken }}')"
                    class="inline-flex h-8 items-center rounded-md bg-emerald-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-500">Copy token</button>
            </div>
            <p class="mt-2 text-[11px] text-emerald-700 dark:text-emerald-300">Paste this into the WordPress plugin's Settings → EBQ SEO panel. You won't be able to see it again — regenerate if lost.</p>
        </div>
    @endif

    {{-- Active tokens --}}
    @if ($tokens instanceof \Illuminate\Support\Collection && $tokens->isNotEmpty())
        <div class="mt-5">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active tokens</p>
            <ul class="mt-2 divide-y divide-slate-100 rounded-md border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                @foreach ($tokens as $token)
                    <li class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800 dark:text-slate-100">{{ $token->name }}</p>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Created {{ $token->created_at?->diffForHumans() }} · Last used {{ $token->last_used_at?->diffForHumans() ?? 'never' }}</p>
                        </div>
                        <button type="button" wire:click="revokeToken({{ $token->id }})"
                            wire:confirm="Revoke this token? The plugin using it will need to re-verify."
                            class="inline-flex h-7 items-center rounded-md border border-red-200 bg-white px-2.5 text-[11px] font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-800 dark:bg-slate-900 dark:text-red-400 dark:hover:bg-red-900/20">Revoke</button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
