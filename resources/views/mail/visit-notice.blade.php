<x-mail::message>
# Hi{{ $firstName !== '' ? ' '.$firstName : '' }},

{{ $intro }}

<x-mail::panel>
**{{ $window }}**
</x-mail::panel>

@if ($after)
{{ $after }}

@endif
It’s for {{ $title }}.

<x-mail::button :url="$url">
See your project
</x-mail::button>

@include('mail.partials.sign-off')
</x-mail::message>
