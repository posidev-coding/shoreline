@props(['media', 'alt', 'sizes' => '100vw', 'eager' => false])
{{-- A public WebP rendition with a srcset of the sizes generated so far (conversions are queued). --}}
@php
    $sources = collect(\App\Models\Showcase::WIDTHS)
        ->filter(fn (int $width) => $media?->hasGeneratedConversion("w{$width}"))
        ->map(fn (int $width) => $media->getUrl("w{$width}").' '.$width.'w');
    $src = $sources->isNotEmpty() ? strtok($sources->last(), ' ') : null;
@endphp
@if ($src)
    <img src="{{ $src }}" srcset="{{ $sources->implode(', ') }}" sizes="{{ $sizes }}" alt="{{ $alt }}"
        @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async"
        {{ $attributes->class('h-full w-full object-cover') }}>
@else
    <div {{ $attributes->class('flex h-full w-full items-center justify-center bg-navy-100 text-sm text-navy-600') }}>Photo processing</div>
@endif
