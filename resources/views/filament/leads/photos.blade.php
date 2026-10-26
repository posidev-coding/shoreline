@php
    /** @var \App\Models\Lead $record */
    $record = $getRecord();
    $photos = $record->getMedia('photos');
    $expires = now()->addMinutes(30);
@endphp

@if ($photos->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No photos. If they sent some, they’ll appear here within a minute.</p>
@else
    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach ($photos as $photo)
            @php
                $full = $photo->getTemporaryUrl($expires);
                $browserCanShow = in_array($photo->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
                $thumb = $photo->hasGeneratedConversion('thumb') ? $photo->getTemporaryUrl($expires, 'thumb') : ($browserCanShow ? $full : null);
            @endphp
            <li>
                <a href="{{ $full }}" target="_blank" rel="noopener" class="group block overflow-hidden rounded-lg ring-1 ring-gray-950/10 dark:ring-white/10">
                    @if ($thumb)
                        <img src="{{ $thumb }}" alt="{{ $photo->name }}" loading="lazy" class="aspect-4/3 w-full object-cover transition group-hover:opacity-90">
                    @else
                        {{-- A HEIC that wouldn't convert: Safari shows it, other browsers download it. --}}
                        <span class="flex aspect-4/3 w-full flex-col items-center justify-center gap-1 bg-gray-50 p-3 text-center text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
                            <x-filament::icon icon="heroicon-o-photo" class="size-6" />
                            {{ $photo->file_name }}
                            <span class="text-xs">Open or download</span>
                        </span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
@endif
