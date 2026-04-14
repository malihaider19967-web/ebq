<div class="mx-auto max-w-2xl space-y-6">
    {{-- Profile --}}
    <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Profile Information</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Update your name and email address.</p>
        </div>
        <form wire:submit="updateProfile" class="space-y-4 px-6 py-5">
            <div>
                <label for="name" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Name</label>
                <input wire:model="name" id="name" type="text"
                    class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                @error('name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Email</label>
                <input wire:model="email" id="email" type="email"
                    class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                @error('email') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center gap-3 pt-1">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Save Changes</button>
                @if ($profileSaved)
                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400" wire:transition>Saved successfully</span>
                @endif
            </div>
        </form>
    </div>

    {{-- Password --}}
    <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Update Password</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Ensure your account uses a strong password.</p>
        </div>
        <form wire:submit="updatePassword" class="space-y-4 px-6 py-5">
            <div>
                <label for="current_password" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Current Password</label>
                <input wire:model="current_password" id="current_password" type="password"
                    class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                @error('current_password') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">New Password</label>
                <input wire:model="password" id="password" type="password"
                    class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
                @error('password') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Confirm Password</label>
                <input wire:model="password_confirmation" id="password_confirmation" type="password"
                    class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800" />
            </div>
            <div class="flex items-center gap-3 pt-1">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Update Password</button>
                @if ($passwordSaved)
                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400" wire:transition>Updated successfully</span>
                @endif
            </div>
        </form>
    </div>

    {{-- Google connection --}}
    <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Google Account</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Connect your Google account for Analytics and Search Console data.</p>
        </div>
        <div class="px-6 py-5">
            @if ($googleAccount)
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-500/10">
                            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Connected</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Token expires {{ $googleAccount->expires_at?->diffForHumans() ?? 'unknown' }}</p>
                        </div>
                    </div>
                    <a href="{{ route('google.redirect') }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Reconnect</a>
                </div>
            @else
                <a href="{{ route('google.redirect') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#fff"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#fff" fill-opacity=".7"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#fff" fill-opacity=".5"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#fff" fill-opacity=".8"/></svg>
                    Connect Google Account
                </a>
            @endif
        </div>
    </div>
</div>
