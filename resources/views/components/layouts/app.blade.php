<!DOCTYPE html>
<html lang="en" x-data="{ dark: false }" x-bind:class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GrowthHub</title>
    @if (! app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="min-h-screen md:flex">
        <aside class="w-full bg-white p-4 shadow md:w-64 dark:bg-slate-900">
            <nav class="space-y-2 text-sm">
                <a class="block" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="block" href="{{ route('keywords.index') }}">Keywords</a>
                <a class="block" href="{{ route('pages.index') }}">Pages</a>
                <a class="block" href="{{ route('websites.index') }}">Websites</a>
                <a class="block" href="{{ route('settings.index') }}">Settings</a>
                <form method="POST" action="{{ route('logout') }}" class="pt-2">
                    @csrf
                    <button type="submit" class="text-left text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Log out</button>
                </form>
            </nav>
        </aside>
        <main class="flex-1 p-4">
            <div class="mb-4 flex items-center justify-between rounded bg-white p-3 shadow dark:bg-slate-900">
                <div class="flex items-center gap-3">
                    <input type="date" class="rounded border p-2 text-sm dark:bg-slate-800" />
                    <input type="date" class="rounded border p-2 text-sm dark:bg-slate-800" />
                </div>
                <button @click="dark = !dark" class="rounded border px-3 py-2 text-sm">Toggle Dark</button>
            </div>
            {{ $slot }}
        </main>
    </div>
</body>
</html>
