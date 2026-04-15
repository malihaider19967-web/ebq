<div class="space-y-6">
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
</div>
