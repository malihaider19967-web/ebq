<x-mail::message>
# Access granted

You now have access to **{{ $website->domain }}** in {{ config('app.name') }}.

<x-mail::button :url="route('dashboard')">
Open dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
