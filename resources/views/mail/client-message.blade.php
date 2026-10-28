<x-mail::message>
# {{ $from }} sent {{ count($messages) === 1 ? 'a message' : count($messages).' messages' }}

About {{ $title }}, for {{ $client }}:

@include('mail.partials.messages')

<x-mail::button :url="$url">
Reply on the job
</x-mail::button>

Replying to this email reaches {{ $firstName }} directly, but only a reply on the job shows in their portal.
</x-mail::message>
