<x-mail::message>
# Hi{{ $firstName !== '' ? ' '.$firstName : '' }},

{{ $businessName }} sent you {{ count($messages) === 1 ? 'a message' : count($messages).' messages' }} about {{ $title }}:

@include('mail.partials.messages')

<x-mail::button :url="$url">
Reply in your portal
</x-mail::button>

@include('mail.partials.sign-off')
</x-mail::message>
