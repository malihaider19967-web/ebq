<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'GrowthHub' }}</title>
    @if (! app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="flex min-h-screen flex-col items-center justify-center p-4">
        <a href="{{ url('/') }}" class="mb-8 text-lg font-semibold tracking-tight text-slate-800 dark:text-slate-100">GrowthHub</a>
        {{ $slot }}
    </div>
</body>
</html>
