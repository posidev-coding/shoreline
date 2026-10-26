@props(['eyebrow' => null, 'title', 'level' => 'h2', 'dark' => false])
<div {{ $attributes->class('max-w-2xl') }}>
    @if ($eyebrow)
        <p @class(['text-sm font-bold tracking-[0.18em] uppercase', 'text-signal-500' => $dark, 'text-signal-700' => ! $dark])>{{ $eyebrow }}</p>
    @endif
    <{{ $level }} @class(['mt-2 font-display text-4xl leading-none font-extrabold tracking-wide uppercase sm:text-5xl', 'text-white' => $dark, 'text-navy-900' => ! $dark])>{{ $title }}</{{ $level }}>
    @if ($slot->isNotEmpty())
        <p @class(['mt-4 text-lg leading-relaxed', 'text-sand-200/90' => $dark, 'text-navy-700' => ! $dark])>{{ $slot }}</p>
    @endif
</div>
