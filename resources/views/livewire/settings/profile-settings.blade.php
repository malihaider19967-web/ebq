<div class="space-y-6">
    {{-- Profile --}}
    <div class="rounded-lg bg-white p-5 shadow dark:bg-slate-900">
        <h2 class="mb-4 text-lg font-semibold">Profile</h2>
        <form wire:submit="updateProfile" class="max-w-md space-y-4">
            <div>
                <label for="name" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                <input wire:model="name" id="name" type="text"
                    class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                <input wire:model="email" id="email" type="email"
                    class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-500">Save</button>
                @if ($profileSaved)
                    <span class="text-sm text-emerald-600 dark:text-emerald-400" wire:transition>Saved.</span>
                @endif
            </div>
        </form>
    </div>

    {{-- Password --}}
    <div class="rounded-lg bg-white p-5 shadow dark:bg-slate-900">
        <h2 class="mb-4 text-lg font-semibold">Change Password</h2>
        <form wire:submit="updatePassword" class="max-w-md space-y-4">
            <div>
                <label for="current_password" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Current Password</label>
                <input wire:model="current_password" id="current_password" type="password"
                    class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">New Password</label>
                <input wire:model="password" id="password" type="password"
                    class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Confirm Password</label>
                <input wire:model="password_confirmation" id="password_confirmation" type="password"
                    class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-500">Update Password</button>
                @if ($passwordSaved)
                    <span class="text-sm text-emerald-600 dark:text-emerald-400" wire:transition>Updated.</span>
                @endif
            </div>
        </form>
    </div>

    {{-- Google connection --}}
    <div class="rounded-lg bg-white p-5 shadow dark:bg-slate-900">
        <h2 class="mb-4 text-lg font-semibold">Google Account</h2>
        @if ($googleAccount)
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Connected
                </span>
                <span class="text-sm text-slate-500 dark:text-slate-400">
                    Token expires {{ $googleAccount->expires_at?->diffForHumans() ?? 'unknown' }}
                </span>
            </div>
            <a href="{{ route('google.redirect') }}" class="mt-3 inline-block text-sm text-indigo-600 hover:underline dark:text-indigo-400">Reconnect</a>
        @else
            <a href="{{ route('google.redirect') }}" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-500">
                Connect Google Account
            </a>
        @endif
    </div>
</div>
