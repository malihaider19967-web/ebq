<div class="space-y-5">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Websites</h1>
        <button wire:click="toggleForm"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-500">
            {{ $showForm ? 'Cancel' : 'Add Website' }}
        </button>
    </div>

    @if ($showForm)
        <div class="rounded-lg bg-white p-5 shadow dark:bg-slate-900">
            <form wire:submit="addWebsite" class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Domain</label>
                    <input wire:model="domain" type="text" placeholder="example.com"
                        class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                    @error('domain') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">GA4 Property ID</label>
                    <input wire:model="gaPropertyId" type="text" placeholder="properties/123456789"
                        class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                    @error('gaPropertyId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">GSC Site URL</label>
                    <input wire:model="gscSiteUrl" type="text" placeholder="sc-domain:example.com"
                        class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
                    @error('gscSiteUrl') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-3">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-500">
                        Save
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($websites->isNotEmpty())
        <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        <th class="px-4 py-3">Domain</th>
                        <th class="px-4 py-3">GA4 Property</th>
                        <th class="px-4 py-3">Search Console</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($websites as $site)
                        <tr class="border-b border-slate-100 dark:border-slate-800">
                            <td class="px-4 py-3 font-medium">{{ $site->domain }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $site->ga_property_id }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $site->gsc_site_url }}</td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="removeWebsite({{ $site->id }})" wire:confirm="Remove this website? All synced data will be deleted."
                                    class="text-sm text-red-600 hover:underline dark:text-red-400">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="rounded-lg bg-white p-8 text-center text-sm text-slate-400 shadow dark:bg-slate-900">
            No websites added yet.
        </div>
    @endif
</div>
