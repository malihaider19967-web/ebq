<x-layouts.app>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Admin Clients</h1>
            <p class="text-sm text-slate-500">Manage client accounts and impersonation.</p>
        </div>

        @if (session('status'))
            <div class="rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <form method="GET" class="flex gap-2">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search name or email" class="w-full rounded border border-slate-300 px-3 py-2 text-sm" />
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Search</button>
        </form>

        <form method="POST" action="{{ route('admin.clients.store') }}" class="rounded border border-slate-200 bg-white p-4">
            @csrf
            <h2 class="mb-3 text-lg font-semibold">Create Client</h2>
            <div class="grid gap-2 md:grid-cols-2">
                <input type="text" name="name" placeholder="Name" class="rounded border border-slate-300 px-3 py-2 text-sm" required />
                <input type="email" name="email" placeholder="Email" class="rounded border border-slate-300 px-3 py-2 text-sm" required />
                <input type="password" name="password" placeholder="Temporary password" class="rounded border border-slate-300 px-3 py-2 text-sm" required />
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_admin" value="1" /> Is admin</label>
            </div>
            <button class="mt-3 rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Create</button>
        </form>

        <div class="space-y-3">
            @foreach ($clients as $client)
                <form method="POST" action="{{ route('admin.clients.update', $client) }}" class="rounded border border-slate-200 bg-white p-4">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-2 md:grid-cols-5">
                        <input type="text" name="name" value="{{ $client->name }}" class="rounded border border-slate-300 px-3 py-2 text-sm md:col-span-2" required />
                        <input type="email" name="email" value="{{ $client->email }}" class="rounded border border-slate-300 px-3 py-2 text-sm md:col-span-2" required />
                        <div class="flex items-center gap-4 text-sm">
                            <label><input type="checkbox" name="is_admin" value="1" @checked($client->is_admin) /> admin</label>
                            <label><input type="checkbox" name="is_disabled" value="1" @checked($client->is_disabled) /> disabled</label>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Save</button>
                        @if (! $client->is_disabled)
                            <a href="#" onclick="event.preventDefault(); document.getElementById('impersonate-{{ $client->id }}').submit();" class="rounded border border-indigo-300 px-3 py-1.5 text-xs font-semibold text-indigo-700">Impersonate</a>
                        @endif
                    </div>
                </form>
                @if (! $client->is_disabled)
                    <form id="impersonate-{{ $client->id }}" method="POST" action="{{ route('admin.clients.impersonate', $client) }}" class="hidden">
                        @csrf
                    </form>
                @endif
            @endforeach
        </div>

        {{ $clients->links() }}
    </div>
</x-layouts.app>
