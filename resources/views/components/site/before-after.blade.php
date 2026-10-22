@props(['before', 'after', 'title'])
{{--
    Drag (or use the arrow keys on) the range input to compare. The input covers the whole image,
    so it's the accessible control; resources/js/app.js mirrors its value into --position.
--}}
<figure {{ $attributes->class('before-after relative isolate aspect-[4/3] overflow-hidden rounded-xl bg-navy-900 select-none') }} style="--position: 50%">
    <x-site.media-image :media="$after" :alt="'After: '.$title" sizes="(min-width: 1024px) 1100px, 100vw" eager class="absolute inset-0" />
    <div class="absolute inset-0 [clip-path:inset(0_calc(100%-var(--position))_0_0)]">
        <x-site.media-image :media="$before" :alt="'Before: '.$title" sizes="(min-width: 1024px) 1100px, 100vw" eager class="absolute inset-0" />
    </div>
    <div aria-hidden="true" class="pointer-events-none absolute inset-y-0 left-[var(--position)] w-1 -translate-x-1/2 bg-signal-500">
        <span class="absolute top-1/2 left-1/2 flex size-10 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-signal-500 text-navy-900 shadow-lg">
            <x-heroicon-o-arrows-right-left class="size-5" />
        </span>
    </div>
    <span class="pointer-events-none absolute top-3 left-3 rounded bg-navy-900/80 px-2 py-1 text-xs font-bold tracking-widest text-sand-100 uppercase">Before</span>
    <span class="pointer-events-none absolute top-3 right-3 rounded bg-navy-900/80 px-2 py-1 text-xs font-bold tracking-widest text-sand-100 uppercase">After</span>
    <input type="range" min="0" max="100" value="50" aria-label="Compare before and after: slide to reveal more of the before photo"
        class="absolute inset-0 z-10 h-full w-full cursor-ew-resize opacity-0" data-before-after>
</figure>
