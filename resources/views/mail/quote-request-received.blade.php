<x-mail::message>
# Thanks{{ $lead->greetingName() ? ', '.$lead->greetingName() : '' }}

We got your quote request{{ $service ? ' for '.$service : '' }}{{ $lead->serviceArea ? ' in '.$lead->serviceArea->name : '' }}, and we’ll get back to you soon by {{ strtolower($lead->preferred_contact->getLabel()) }}.

@if ($isUrgent && $phone)
If your door is stuck or unsafe right now, please call us at [{{ $phone }}]({{ $phoneHref }}).
@elseif ($phone)
If anything changes in the meantime, call us at [{{ $phone }}]({{ $phoneHref }}), or reply to this email.
@else
If anything changes in the meantime, reply to this email.
@endif

Thanks,<br>
{{ $businessName }}

<x-mail::subcopy>
You’re getting this because this address was entered on the quote form at {{ parse_url(config('app.url'), PHP_URL_HOST) }}. If that wasn’t you, you can ignore this email.
</x-mail::subcopy>
</x-mail::message>
