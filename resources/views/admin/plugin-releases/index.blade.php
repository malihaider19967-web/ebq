<x-layouts.app>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Plugin Releases</h1>
            <p class="text-sm text-slate-500">Upload and control WordPress plugin versions.</p>
        </div>

        @if (session('status'))
            <div class="rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.plugin-releases.store') }}" enctype="multipart/form-data" class="rounded border border-slate-200 bg-white p-4">
            @csrf
            <div class="grid gap-2 md:grid-cols-3">
                <input type="text" name="version" placeholder="Version e.g. 2.3.0" class="rounded border border-slate-300 px-3 py-2 text-sm" required />
                <select name="channel" class="rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="stable">stable</option>
                    <option value="beta">beta</option>
                </select>
                <select name="publish_mode" class="rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="draft">draft</option>
                    <option value="now">publish now</option>
                    <option value="schedule">schedule</option>
                </select>
                <input type="datetime-local" name="publish_at" class="rounded border border-slate-300 px-3 py-2 text-sm" />
                <input type="file" name="zip" accept=".zip" class="rounded border border-slate-300 px-3 py-2 text-sm md:col-span-2" required />
                <textarea name="release_notes" rows="3" placeholder="Release notes" class="rounded border border-slate-300 px-3 py-2 text-sm md:col-span-3"></textarea>
            </div>
            <button class="mt-3 rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Create Release</button>
        </form>

        <div class="overflow-auto rounded border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-3 py-2">Version</th>
                        <th class="px-3 py-2">Channel</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Publish At</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($releases as $release)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $release->version }}</td>
                            <td class="px-3 py-2">{{ $release->channel }}</td>
                            <td class="px-3 py-2">{{ $release->status }}</td>
                            <td class="px-3 py-2">{{ $release->publish_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('admin.plugin-releases.publish', $release) }}">@csrf<button class="rounded border border-indigo-300 px-2 py-1 text-xs">Publish</button></form>
                                    <form method="POST" action="{{ route('admin.plugin-releases.rollback', $release) }}">@csrf<button class="rounded border border-amber-300 px-2 py-1 text-xs">Rollback</button></form>
                                    <form method="POST" action="{{ route('admin.plugin-releases.destroy', $release) }}">@csrf @method('DELETE')<button class="rounded border border-red-300 px-2 py-1 text-xs">Delete</button></form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $releases->links() }}
    </div>
</x-layouts.app>
