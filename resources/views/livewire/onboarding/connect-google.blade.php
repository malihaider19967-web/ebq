<div class="space-y-3">
    <a href="{{ route('google.redirect') }}" class="inline-block rounded bg-blue-600 px-4 py-2 text-white">Connect Google Account</a>
    <input wire:model="domain" class="block w-full rounded border p-2" placeholder="Domain" />
    <input wire:model="gaPropertyId" class="block w-full rounded border p-2" placeholder="GA Property ID" />
    <input wire:model="gscSiteUrl" class="block w-full rounded border p-2" placeholder="GSC Site URL" />
    <button wire:click="saveSelection" class="rounded bg-slate-900 px-4 py-2 text-white">Save Selection</button>
</div>
