<div>
    @if (count($websites) > 1)
        <select wire:model.live="websiteId"
            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800">
            @foreach ($websites as $site)
                <option value="{{ $site['id'] }}">{{ $site['domain'] }}</option>
            @endforeach
        </select>
    @elseif (count($websites) === 1)
        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $websites[0]['domain'] }}</span>
    @endif
</div>
