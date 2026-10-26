@props(['href', 'variant' => 'primary', 'size' => 'md'])
@php
    $variants = [
        // Navy text on safety orange: white on orange fails contrast.
        'primary' => 'bg-signal-500 text-navy-900 hover:bg-signal-400',
        'outline-light' => 'text-sand-100 ring-2 ring-inset ring-sand-200/60 hover:bg-white/10',
        'dark' => 'bg-navy-900 text-sand-100 hover:bg-navy-800',
    ];
    $sizes = [
        'sm' => 'px-4 py-2 text-sm',
        'md' => 'px-6 py-3 text-base',
        'lg' => 'px-7 py-4 text-lg',
    ];
@endphp
<a href="{{ $href }}" {{ $attributes->class(['inline-flex items-center justify-center gap-2 rounded-md font-bold tracking-wide transition', $variants[$variant], $sizes[$size]]) }}>{{ $slot }}</a>
