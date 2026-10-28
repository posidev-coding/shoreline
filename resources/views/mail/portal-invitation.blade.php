<x-mail::message>
# Hi{{ $firstName !== '' ? ' '.$firstName : '' }},

{{ $businessName }} has set up a page where you can see your projects with us and when we’re coming out.

<x-mail::button :url="$signInUrl">
See your projects
</x-mail::button>

There’s no password. Sign in with this email address, {{ $user->email }}, and we’ll email you a code each time.

@if ($phone)
Questions? Call us at [{{ $phone }}]({{ $phoneHref }}), or reply to this email.
@else
Questions? Reply to this email.
@endif

Thanks,<br>
{{ $businessName }}
</x-mail::message>
