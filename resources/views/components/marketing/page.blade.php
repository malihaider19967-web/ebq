@props([
    'title' => 'EBQ',
    'description' => 'EBQ — clear SEO operations with rankings, backlinks, audits, and AI-powered content tools.',
    'canonical' => null,
    'active' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">

    @include('partials.favicon-links')

    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="EBQ">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @if (! app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-full bg-slate-950 font-sans text-slate-50 antialiased selection:bg-indigo-500/30 selection:text-white">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-950">Skip to content</a>

    <div class="relative overflow-x-clip">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[34rem] bg-[radial-gradient(circle_at_20%_0,rgba(99,102,241,0.28),transparent_45%),radial-gradient(circle_at_80%_0,rgba(14,165,233,0.22),transparent_40%)]"></div>

        <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/85 backdrop-blur">
            <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
                <a href="{{ route('landing') }}" class="inline-flex items-center" aria-label="EBQ home">
                    <img src="{{ asset('logo.png') }}" alt="EBQ" width="48" height="48" class="h-12 w-12 rounded-lg">
                </a>

                <nav aria-label="Primary" class="hidden items-center gap-8 text-sm font-medium text-slate-100 md:flex">
                    <a href="{{ route('features') }}" class="transition hover:text-indigo-200 {{ $active === 'features' ? 'text-indigo-200' : '' }}">Features</a>
                    <a href="{{ route('pricing') }}" class="transition hover:text-indigo-200 {{ $active === 'pricing' ? 'text-indigo-200' : '' }}">Pricing</a>
                    <a href="{{ route('landing') }}#wordpress" class="transition hover:text-indigo-200">WordPress</a>
                    <a href="{{ route('landing') }}#faq" class="transition hover:text-indigo-200">FAQ</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="hidden rounded-md px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 sm:inline-flex">Sign in</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">Start free</a>
                </div>
            </div>
        </header>

        <main id="main">
            {{ $slot }}
        </main>

        <footer class="border-t border-white/10 bg-slate-950">
            <div class="mx-auto grid max-w-7xl gap-10 px-6 py-12 text-sm text-slate-200 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
                <div>
                    <a href="{{ route('landing') }}" class="inline-flex items-center" aria-label="EBQ home">
                        <img src="{{ asset('logo.png') }}" alt="EBQ" width="48" height="48" class="h-12 w-12 rounded-lg">
                    </a>
                    <p class="mt-3 text-slate-400">SEO workspace for teams that ship.</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Product</p>
                    <ul class="mt-3 space-y-2">
                        <li><a class="hover:text-indigo-200" href="{{ route('features') }}">Features</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('pricing') }}">Pricing</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('landing') }}#wordpress">WordPress plugin</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Company</p>
                    <ul class="mt-3 space-y-2">
                        <li><a class="hover:text-indigo-200" href="mailto:hello@ebq.io">Contact</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('landing') }}#faq">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Legal</p>
                    <ul class="mt-3 space-y-2">
                        <li><a class="hover:text-indigo-200" href="{{ route('terms-conditions') }}">Terms &amp; Conditions</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('privacy-policy') }}">Privacy Policy</a></li>
                        <li><a class="hover:text-indigo-200" href="{{ route('refund-policy') }}">Refund Policy</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/5">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-6 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                    <p>&copy; {{ date('Y') }} EBQ. All rights reserved.</p>
                    <p>Built for modern SEO teams.</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
