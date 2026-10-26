@php
    /** @var \App\Models\Lead $record */
    $record = $getRecord();
    $photos = $record->getMedia('photos');
@endphp

@if ($photos->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No photos. If they sent some, they’ll appear here within a minute.</p>
@else
    @include('filament.partials.photo-grid', ['photos' => $photos])
@endif
