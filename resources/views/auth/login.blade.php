<x-layouts.guest>
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h1>
        <p class="mt-2 text-sm text-slate-600">Sign in to your EBQ account</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-xs font-medium text-slate-700">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
            @error('email')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-xs font-medium text-slate-700">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
            @error('password')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center">
            <input id="remember" name="remember" type="checkbox" value="1"
                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
            <label for="remember" class="ml-2 text-sm text-slate-600">Remember me</label>
        </div>

        <button type="submit"
            class="flex w-full justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">
            Sign in
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-600">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-semibold text-slate-900 underline-offset-2 hover:underline">Create one</a>
    </p>
</x-layouts.guest>
