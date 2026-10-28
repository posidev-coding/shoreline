<x-mail::message>
# Your sign-in code

Enter this code on the sign-in page:

<x-mail::panel>
<span style="font-size: 28px; font-weight: 700; letter-spacing: 6px;">{{ $code }}</span>
</x-mail::panel>

Or sign in with the button:

<x-mail::button :url="$confirmUrl">
Sign in
</x-mail::button>

The code and the button work once, for the next {{ $minutes }} minutes.

Thanks,<br>
{{ $businessName }}

<x-mail::subcopy>
Someone asked to sign in to {{ parse_url(config('app.url'), PHP_URL_HOST) }} with this address. If that wasn’t you, you can ignore this email: no one can sign in without the code.
</x-mail::subcopy>
</x-mail::message>
