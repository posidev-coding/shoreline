@props(['title', 'breadcrumbs' => [], 'eyebrow' => null])
<section class="relative overflow-hidden bg-navy-900 text-sand-200">
    {{-- Door-panel texture --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-[0.07] [background-image:repeating-linear-gradient(to_bottom,transparent_0,transparent_46px,#e8dcc4_46px,#e8dcc4_48px)]"></div>
    <div class="relative mx-auto max-w-6xl px-4 pt-10 pb-12 sm:px-6 lg:pt-14 lg:pb-16">
        @if ($breadcrumbs !== [])
            <nav aria-label="Breadcrumb" class="text-sm">
                <ol class="flex flex-wrap items-center gap-2 text-sand-200/70">
                    <li><a href="{{ route('home') }}" class="hover:text-white">Home</a></li>
                    @foreach ($breadcrumbs as $crumb)
                        <li aria-hidden="true">/</li>
                        <li>
                            @if ($loop->last)
                                <span aria-current="page" class="text-sand-100">{{ $crumb['name'] }}</span>
                            @else
                                <a href="{{ $crumb['url'] }}" class="hover:text-white">{{ $crumb['name'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        @if ($eyebrow)
            <p class="mt-8 text-sm font-bold tracking-[0.18em] text-signal-500 uppercase">{{ $eyebrow }}</p>
        @endif
        <h1 @class(['font-display text-5xl leading-[0.95] font-extrabold tracking-wide text-white uppercase sm:text-6xl lg:text-7xl', 'mt-2' => $eyebrow, 'mt-8' => ! $eyebrow])>{{ $title }}</h1>
        @if ($slot->isNotEmpty())
            <div class="mt-5 max-w-2xl text-lg leading-relaxed text-sand-200/90">{{ $slot }}</div>
        @endif
    </div>
    <div aria-hidden="true" class="h-1.5 bg-signal-500"></div>
</section>
