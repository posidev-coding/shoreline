@props(['showcase'])
<a href="{{ route('work.show', $showcase) }}" {{ $attributes->class('group block overflow-hidden rounded-xl border border-navy-100 bg-white shadow-sm transition hover:border-signal-500 hover:shadow-md') }}>
    <div class="aspect-[4/3] overflow-hidden">
        <x-site.media-image :media="$showcase->getFirstMedia('after')" :alt="'After: '.$showcase->title"
            sizes="(min-width: 1024px) 360px, (min-width: 640px) 50vw, 100vw" class="transition duration-500 group-hover:scale-[1.03]" />
    </div>
    <div class="p-5">
        <p class="text-xs font-bold tracking-[0.16em] text-signal-700 uppercase">
            {{ collect([$showcase->service?->name, $showcase->serviceArea?->name])->filter()->implode(' · ') }}
        </p>
        <h3 class="mt-1 text-lg font-bold text-navy-900">{{ $showcase->title }}</h3>
    </div>
</a>
