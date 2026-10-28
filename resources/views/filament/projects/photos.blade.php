@php
    /** @var \App\Models\Project $record */
    $record = $getRecord();
    $photos = $record->getMedia('photos');
    $requestPhotos = $record->lead?->getMedia('photos') ?? collect();
@endphp

<div class="space-y-4">
    @if ($photos->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No photos yet. Add them with Edit, from your phone on site.</p>
    @else
        @include('filament.partials.photo-grid', ['photos' => $photos, 'showSharing' => true])
    @endif

    @if ($requestPhotos->isNotEmpty())
        <p class="text-sm font-medium text-gray-950 dark:text-white">Sent with the quote request</p>
        @include('filament.partials.photo-grid', ['photos' => $requestPhotos])
    @endif
</div>
