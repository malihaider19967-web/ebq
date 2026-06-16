<x-layouts.app>
    <div class="space-y-5">
        {{-- Page header --}}
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Crawler</h1>
            <p class="text-sm text-slate-500">Live crawl progress across every domain — one shared crawl per domain, shown with queue backlog and health.</p>
        </div>

        <livewire:admin.crawler-progress />
    </div>
</x-layouts.app>
