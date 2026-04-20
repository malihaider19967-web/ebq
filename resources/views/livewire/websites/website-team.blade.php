<div wire:key="website-team-{{ $websiteId }}-{{ $useSessionWebsite ? 'session' : 'embed' }}">
    @if ($useSessionWebsite)
        <div class="mx-auto max-w-3xl space-y-2">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Team &amp; invites</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Invites and members apply to the site currently selected in the header. Switch sites there to manage another property.
            </p>
        </div>
    @endif

    <div @class([
        'rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900',
        'mt-6' => $useSessionWebsite,
        'mt-4 border-t border-slate-100 pt-4 dark:border-slate-800' => ! $useSessionWebsite,
    ])>
        @if ($emptyReason === 'select_website')
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <svg class="h-12 w-12 text-slate-300 dark:text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3" /></svg>
                <p class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-300">Select a website</p>
                <p class="mt-1 max-w-sm text-xs text-slate-500 dark:text-slate-400">Choose a site from the dropdown in the header, then return here to invite teammates.</p>
            </div>
        @elseif ($emptyReason === 'no_access')
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                You do not have access to this website, or it no longer exists. Select another site in the header or open a site you own or have been invited to.
            </div>
        @elseif ($website)
            @if (! $useSessionWebsite)
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Team</h4>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Invite collaborators by email. They can view and manage this site only.</p>
            @else
                <div class="mb-6 flex flex-wrap items-end justify-between gap-4 border-b border-slate-100 pb-6 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $website->domain }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $website->gsc_site_url }}</p>
                    </div>
                    @if ($readonly)
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">View only</span>
                    @endif
                </div>
            @endif

            @if ($readonly)
                <p class="mb-6 text-sm text-slate-600 dark:text-slate-300">
                    Only the site owner can send invites or remove members. You can still see who has access below.
                </p>
            @endif

            @if (! $readonly)
                <form wire:submit="inviteMember" class="mt-3">
                    <label for="invite-email-{{ $websiteId }}" class="mb-1 block text-[11px] font-medium text-slate-600 dark:text-slate-400">Email address</label>
                    <div class="flex items-center gap-2">
                        <input id="invite-email-{{ $websiteId }}" wire:model="inviteEmail" type="email" placeholder="colleague@example.com" autocomplete="email"
                            class="h-8 min-w-0 flex-1 rounded-md border border-slate-200 bg-white px-2.5 text-xs shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                        <button type="submit" class="inline-flex h-8 shrink-0 items-center rounded-md bg-indigo-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                            Send invite
                        </button>
                    </div>
                    @error('inviteEmail')
                        <p class="mt-0.5 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </form>
            @endif

            <div class="mt-8">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Members</h3>
                <ul class="mt-3 space-y-2">
                    <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2.5 text-sm dark:bg-slate-800/50">
                        <div>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $website->user->name }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $website->user->email }}</span>
                        </div>
                        <span class="rounded-md bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-200">Owner</span>
                    </li>
                    @foreach ($website->members as $member)
                        <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2.5 text-sm dark:bg-slate-800/50">
                            <div>
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ $member->name }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $member->email }}</span>
                            </div>
                            @if (! $readonly)
                                <button type="button" wire:click="revokeMember({{ $member->id }})" wire:confirm="Remove this member from the site?"
                                    class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                                    Remove
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            @if (session('team_status'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                    class="mt-4 flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                    <span>{{ session('team_status') }}</span>
                    <button @click="show = false" class="text-emerald-700/60">×</button>
                </div>
            @endif
            @if (session('team_error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
                    class="mt-4 flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    <span>{{ session('team_error') }}</span>
                    <button @click="show = false" class="text-amber-800/60">×</button>
                </div>
            @endif

            @if ($website->invitations->isNotEmpty())
                <div class="mt-8">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pending invitations</h3>
                    <ul class="mt-3 space-y-2">
                        @foreach ($website->invitations as $inv)
                            @php
                                $expired = $inv->expires_at && $inv->expires_at->isPast();
                                $sentAt = $inv->updated_at ?? $inv->created_at;
                            @endphp
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-dashed border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ $inv->email }}</span>
                                        @if ($expired)
                                            <span class="rounded-full bg-red-50 px-2 py-px text-[10px] font-semibold uppercase text-red-600 dark:bg-red-500/10 dark:text-red-400">Expired</span>
                                        @else
                                            <span class="rounded-full bg-amber-50 px-2 py-px text-[10px] font-semibold uppercase text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">Pending</span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        @if ($sentAt)Sent {{ $sentAt->diffForHumans() }}@endif
                                        @if ($inv->expires_at)
                                            · {{ $expired ? 'Expired' : 'Expires' }} {{ $inv->expires_at->diffForHumans() }}
                                        @endif
                                    </div>
                                </div>
                                @if (! $readonly)
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button type="button"
                                            wire:click="resendInvitation({{ $inv->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="resendInvitation({{ $inv->id }})"
                                            class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60">
                                            <svg wire:loading.remove wire:target="resendInvitation({{ $inv->id }})" class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 2.51-4.66-2.51m0 0-1.023-.55a2.25 2.25 0 0 0-2.134 0l-1.022.55m0 0-4.661 2.51m16.5 1.615a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V8.844a2.25 2.25 0 0 1 1.183-1.98l7.5-4.04a2.25 2.25 0 0 1 2.134 0l7.5 4.04a2.25 2.25 0 0 1 1.183 1.98V19.5Z" /></svg>
                                            <svg wire:loading wire:target="resendInvitation({{ $inv->id }})" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"></circle><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path></svg>
                                            <span wire:loading.remove wire:target="resendInvitation({{ $inv->id }})">Resend</span>
                                            <span wire:loading wire:target="resendInvitation({{ $inv->id }})">Sending…</span>
                                        </button>
                                        <button type="button" wire:click="cancelInvitation({{ $inv->id }})" wire:confirm="Cancel this invitation?"
                                            class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                            Cancel
                                        </button>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </div>
</div>
