<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'EBQ' }}</title>
    @include('partials.favicon-links')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @if (! app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="h-full bg-gradient-to-br from-slate-50 via-white to-indigo-50 text-slate-900 antialiased">
    <div class="flex min-h-full">
        <div class="hidden w-1/2 bg-indigo-600 lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div>
                <span class="text-2xl font-bold text-white">EBQ</span>
                <p class="mt-2 text-indigo-200">SEO & Analytics Dashboard</p>
            </div>
            <div>
                <blockquote class="text-lg font-medium leading-relaxed text-indigo-100">
                    "Track your search performance, analyze keywords, and grow your organic traffic — all in one place."
                </blockquote>
                <div class="mt-6 flex gap-4">
                    <div class="rounded-lg bg-indigo-500/30 px-4 py-3 text-center">
                        <p class="text-2xl font-bold text-white">GA4</p>
                        <p class="text-xs text-indigo-200">Analytics</p>
                    </div>
                    <div class="rounded-lg bg-indigo-500/30 px-4 py-3 text-center">
                        <p class="text-2xl font-bold text-white">GSC</p>
                        <p class="text-xs text-indigo-200">Search Console</p>
                    </div>
                    <div class="rounded-lg bg-indigo-500/30 px-4 py-3 text-center">
                        <p class="text-2xl font-bold text-white">SEO</p>
                        <p class="text-xs text-indigo-200">Insights</p>
                    </div>
                </div>
            </div>
            <p class="text-xs text-indigo-300">&copy; {{ date('Y') }} EBQ. All rights reserved.</p>
        </div>
        <div class="flex w-full flex-col items-center justify-center p-6 lg:w-1/2 lg:p-12">
            <div class="mb-8 lg:hidden">
                <span class="text-xl font-bold text-indigo-600">EBQ</span>
            </div>
            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
