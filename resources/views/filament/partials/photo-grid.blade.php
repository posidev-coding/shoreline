@php
    use App\Models\Project;
    use App\Support\PrivateMedia;

    /**
     * Private photos through short-lived links. A job's own photos say which show in the portal.
     *
     * @var \Illuminate\Support\Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $photos
     */
    $expires = PrivateMedia::expiry();
    $showSharing ??= false;
@endphp

<ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
    @foreach ($photos as $photo)
        @php
            $thumb = PrivateMedia::thumbnailUrl($photo, $expires);
        @endphp
        <li class="relative">
            <a href="{{ PrivateMedia::url($photo, $expires) }}" target="_blank" rel="noopener" class="group block overflow-hidden rounded-lg ring-1 ring-gray-950/10 dark:ring-white/10">
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
            @if ($showSharing && Project::isInPortal($photo))
                <span class="pointer-events-none absolute top-2 left-2 rounded-md bg-white/90 px-1.5 py-0.5 text-xs font-medium text-gray-950 shadow-sm ring-1 ring-gray-950/10">In the portal</span>
            @endif
        </li>
    @endforeach
</ul>
