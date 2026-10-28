<x-mail::message>
# Hi{{ $firstName !== '' ? ' '.$firstName : '' }},

We’ve finished {{ $title }} at {{ $street }}. Thanks for having us.

@if ($hasFiles)
Your project page has the photos and documents from the job.
@else
Your project page has the details of the job.
@endif

<x-mail::button :url="$url">
See your project
</x-mail::button>

@include('mail.partials.sign-off')
</x-mail::message>
