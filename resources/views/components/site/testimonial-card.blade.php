@props(['testimonial'])
<figure {{ $attributes->class('flex h-full flex-col rounded-xl border border-navy-100 bg-white p-6 shadow-sm') }}>
    @if ($testimonial->rating)
        <div class="flex gap-0.5 text-signal-600" role="img" aria-label="Rated {{ $testimonial->rating }} out of 5">
            @for ($star = 1; $star <= 5; $star++)
                <x-heroicon-s-star @class(['size-5', 'text-navy-100' => $star > $testimonial->rating]) aria-hidden="true" />
            @endfor
        </div>
    @endif
    <blockquote class="mt-4 flex-1 text-lg leading-relaxed text-navy-800">“{{ $testimonial->quote }}”</blockquote>
    <figcaption class="mt-5 text-sm text-navy-700">
        <span class="font-bold text-navy-900">{{ $testimonial->author_name }}</span>
        @if ($testimonial->serviceArea) · {{ $testimonial->serviceArea->name }} @endif
        <span class="block text-xs text-navy-600">{{ $testimonial->source->attribution() }}@if ($testimonial->reviewed_on), {{ $testimonial->reviewed_on->format('F Y') }}@endif</span>
    </figcaption>
</figure>
