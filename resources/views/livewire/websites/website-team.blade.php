<div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800" wire:key="website-team-{{ $websiteId }}">
    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Team</h4>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Invite collaborators by email. They can view and manage this site only.</p>

    <form wire:submit="inviteMember" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <label for="invite-email-{{ $websiteId }}" class="sr-only">Email</label>
            <input id="invite-email-{{ $websiteId }}" wire:model="inviteEmail" type="email" placeholder="colleague@example.com"
                class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
            @error('inviteEmail')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">
            Invite
        </button>
    </form>

    @if ($website->members->isNotEmpty())
        <ul class="mt-4 space-y-2">
            @foreach ($website->members as $member)
                <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50">
                    <div>
                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ $member->name }}</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $member->email }}</span>
                    </div>
                    <button type="button" wire:click="revokeMember({{ $member->id }})" wire:confirm="Remove this member?"
                        class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                        Remove
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($website->invitations->isNotEmpty())
        <div class="mt-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Pending invitations</p>
            <ul class="mt-2 space-y-2">
                @foreach ($website->invitations as $inv)
                    <li class="flex items-center justify-between rounded-lg border border-dashed border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span class="text-slate-700 dark:text-slate-300">{{ $inv->email }}</span>
                        <button type="button" wire:click="cancelInvitation({{ $inv->id }})"
                            class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                            Cancel
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
