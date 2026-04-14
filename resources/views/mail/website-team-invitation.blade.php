<x-mail::message>
# Website invitation

You've been invited to collaborate on **{{ $invitation->website->domain }}** in {{ config('app.name') }}.

<x-mail::button :url="route('register', ['invite' => $plainToken])">
Create your account
</x-mail::button>

This invitation expires on {{ $invitation->expires_at->toFormattedDateString() }}.

If you did not expect this email, you can ignore it.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
