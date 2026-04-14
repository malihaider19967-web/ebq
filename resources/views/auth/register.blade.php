<x-layouts.guest>
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Create your account</h1>
        <p class="mt-2 text-sm text-slate-600">Start tracking your SEO performance today</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">
        @csrf
        @if (! empty($inviteToken))
            <input type="hidden" name="invite" value="{{ $inviteToken }}" />
        @endif

        @if (! empty($invitationEmail))
            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                You are registering to accept access to a website. Use the email address this invitation was sent to.
            </div>
        @endif

        <div>
            <label for="name" class="mb-1.5 block text-xs font-medium text-slate-700">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name"
                class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
            @error('name')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-xs font-medium text-slate-700">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $invitationEmail ?? '') }}" required autocomplete="username"
                @if(! empty($invitationEmail)) readonly @endif
                class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
            @error('email')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-xs font-medium text-slate-700">Password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
            @error('password')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-xs font-medium text-slate-700">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
        </div>

        <button type="submit"
            class="flex w-full justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Create account
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-600">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-800">Sign in</a>
    </p>
</x-layouts.guest>
