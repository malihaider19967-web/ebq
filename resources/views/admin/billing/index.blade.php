<x-layouts.app>
    <x-admin.plugin-tabs current="billing" />

    @php
        /**
         * @var \Illuminate\Pagination\LengthAwarePaginator $websites
         * @var array $summary
         * @var string $q
         * @var string $status
         */
        $relTime = function (?\Illuminate\Support\Carbon $when): string {
            if (! $when) return '—';
            try { return $when->diffForHumans(); }
            catch (\Throwable) { return '—'; }
        };
    @endphp

    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold">Subscriptions</h2>
            <p class="text-sm text-slate-500">
                Per-website subscription state. Updated by Stripe webhooks
                (`/stripe/webhook`) on every billing event. Read-only here —
                use the Stripe Dashboard for refunds, manual upgrades, or
                immediate cancellations.
            </p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-lg bg-white border border-slate-200 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total websites</div>
                <div class="text-2xl font-bold text-slate-900 mt-1">{{ number_format((int) $summary['total']) }}</div>
            </div>
            <div class="rounded-lg bg-violet-50 border border-violet-200 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-violet-700">Pro</div>
                <div class="text-2xl font-bold text-violet-900 mt-1">{{ number_format((int) $summary['pro']) }}</div>
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">In trial</div>
                <div class="text-2xl font-bold text-amber-900 mt-1">{{ number_format((int) $summary['on_trial']) }}</div>
            </div>
            <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-700">Free</div>
                <div class="text-2xl font-bold text-slate-900 mt-1">{{ number_format((int) $summary['free']) }}</div>
            </div>
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ $q }}"
                   placeholder="Search by domain"
                   class="text-sm rounded border border-slate-300 px-3 py-1.5" />
            <select name="status" class="text-sm rounded border border-slate-300 px-3 py-1.5">
                <option value="all"    @selected($status === 'all')>All</option>
                <option value="paying" @selected($status === 'paying')>Pro / paying</option>
                <option value="trial"  @selected($status === 'trial')>In trial</option>
                <option value="free"   @selected($status === 'free')>Free</option>
            </select>
            <button class="text-sm rounded bg-slate-800 px-3 py-1.5 font-semibold text-white">Filter</button>
        </form>

        <div class="overflow-auto rounded border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium text-slate-600">Website</th>
                        <th class="px-3 py-2 font-medium text-slate-600">Tier</th>
                        <th class="px-3 py-2 font-medium text-slate-600">Card</th>
                        <th class="px-3 py-2 font-medium text-slate-600">Trial ends</th>
                        <th class="px-3 py-2 font-medium text-slate-600">Stripe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($websites as $website)
                        <tr>
                            <td class="px-3 py-2.5">
                                <div class="font-medium text-slate-900">{{ $website->domain }}</div>
                                <div class="text-xs text-slate-500">
                                    @if ($website->owner)
                                        {{ $website->owner->name ?: $website->owner->email }}
                                    @else
                                        <span class="italic text-slate-400">no owner</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2.5">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-violet-100 text-violet-800' => $website->tier === 'pro',
                                    'bg-slate-100 text-slate-700' => $website->tier !== 'pro',
                                ])>
                                    {{ $website->tier }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($website->pm_last_four)
                                    <span class="text-xs text-slate-700">{{ ucfirst((string) $website->pm_type) }} ···· {{ $website->pm_last_four }}</span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($website->trial_ends_at)
                                    <span class="text-xs {{ $website->trial_ends_at->isFuture() ? 'text-amber-700' : 'text-slate-500' }}">
                                        {{ $relTime($website->trial_ends_at) }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($website->stripe_id)
                                    <a href="https://dashboard.stripe.com/customers/{{ $website->stripe_id }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="text-xs font-mono text-indigo-700 hover:underline">
                                        {{ \Illuminate\Support\Str::limit($website->stripe_id, 18) }} ↗
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400">no customer</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-12 text-center text-sm text-slate-500">
                                @if ($q !== '' || $status !== 'all')
                                    No websites match this filter.
                                @else
                                    No websites yet. Once a customer connects their site and picks a paid plan, they'll show up here.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $websites->links() }}
        </div>
    </div>
</x-layouts.app>
