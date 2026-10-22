<x-layouts.site :seo="$seo">
    <section class="relative isolate overflow-hidden bg-navy-900 text-sand-200">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 opacity-[0.06] [background-image:repeating-linear-gradient(to_bottom,transparent_0,transparent_78px,#e8dcc4_78px,#e8dcc4_80px)]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -right-24 bottom-0 -z-10 hidden size-[34rem] translate-y-1/2 rounded-full bg-signal-500/15 blur-3xl lg:block"></div>

        <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 pt-14 pb-20 sm:px-6 lg:grid-cols-12 lg:pt-24 lg:pb-28">
            <div class="lg:col-span-7">
                <p class="text-sm font-bold tracking-[0.18em] text-signal-500 uppercase">Morehead City &middot; Carteret County &middot; Swansboro &middot; Havelock</p>
                <h1 class="mt-4 font-display text-6xl leading-[0.9] font-extrabold tracking-wide text-white uppercase sm:text-7xl lg:text-8xl">{{ $site->hero_headline }}</h1>
                <p class="mt-6 max-w-xl text-xl leading-relaxed text-sand-200/90">{{ $site->hero_subhead }}</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <x-site.button :href="route('quote')" size="lg">Get a quote <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" /></x-site.button>
                    <x-site.button :href="$site->phoneHref()" variant="outline-light" size="lg">
                        <x-heroicon-o-phone class="size-5 text-signal-500" aria-hidden="true" /> {{ $site->phoneForDisplay() }}
                    </x-site.button>
                </div>
            </div>

            {{-- A garage opening with the sun coming up behind a half-raised door: the brand mark, drawn large. --}}
            <div class="hidden lg:col-span-5 lg:block" aria-hidden="true">
                <svg viewBox="0 0 64 64" class="ml-auto w-full max-w-sm">
                    <clipPath id="hero-sky"><rect x="16" y="17" width="32" height="18" /></clipPath>
                    <circle cx="32" cy="35" r="10" fill="#ff6b1a" clip-path="url(#hero-sky)" />
                    <path d="M13 52V19a4 4 0 0 1 4-4h30a4 4 0 0 1 4 4v33" fill="none" stroke="#e8dcc4" stroke-width="2.5" stroke-linejoin="round" />
                    <rect x="17" y="36" width="30" height="7" rx="1" fill="#e8dcc4" />
                    <rect x="17" y="45" width="30" height="7" rx="1" fill="#e8dcc4" />
                    <path d="M8 56h48" stroke="#e8dcc4" stroke-opacity=".35" stroke-width="1" />
                </svg>
            </div>
        </div>

        {{--
            The door-rollup: a closed sectional door over the hero that rolls up once on load.
            Pure CSS gradients (never an image, so it can't become the LCP element), and not
            rendered at all for visitors who prefer reduced motion.
        --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 z-20 flex flex-col motion-safe:animate-door-rollup motion-reduce:hidden">
            @for ($panel = 0; $panel < 4; $panel++)
                <div class="flex-1 border-b-2 border-sand-300 bg-[linear-gradient(to_bottom,#f4eee2_0%,#e8dcc4_55%,#d8c6a3_100%)] shadow-[inset_0_2px_0_rgb(255_255_255/0.6)]">
                    <div class="mx-auto grid h-full max-w-6xl grid-cols-4 gap-3 px-4 py-[clamp(0.5rem,2vh,1.25rem)] sm:px-6">
                        @for ($inset = 0; $inset < 4; $inset++)
                            <div class="rounded-sm bg-[linear-gradient(to_bottom,#e8dcc4,#f4eee2)] shadow-[inset_0_1px_2px_rgb(11_31_51/0.18)]"></div>
                        @endfor
                    </div>
                </div>
            @endfor
            <div class="h-2 bg-signal-500"></div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <x-site.section-heading eyebrow="What we do" title="Garage doors, top to bottom">
            Installs, repairs, springs, openers, and wind-rated doors, plus the small jobs around the house while we’re there.
        </x-site.section-heading>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($services as $service)
                <x-site.service-card :service="$service" />
            @endforeach
        </div>
    </section>

    <section class="border-y border-navy-100 bg-white">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:grid-cols-3">
            @foreach ([
                ['title' => 'Local to the Crystal Coast', 'body' => 'Based in Morehead City and working across Carteret County, plus Swansboro and Havelock.', 'icon' => 'heroicon-o-map-pin'],
                ['title' => 'Built for salt air', 'body' => 'Coastal doors wear differently. We’ll tell you what holds up near the water and what doesn’t.', 'icon' => 'heroicon-o-sun'],
                ['title' => 'Doors and the odd jobs', 'body' => 'Garage doors are the main thing. While we’re there, we can handle the small repairs on your list too.', 'icon' => 'heroicon-o-wrench'],
            ] as $point)
                <div class="flex gap-4">
                    <x-dynamic-component :component="$point['icon']" class="size-8 shrink-0 text-signal-700" aria-hidden="true" />
                    <div>
                        <h3 class="font-display text-2xl font-extrabold tracking-wide uppercase">{{ $point['title'] }}</h3>
                        <p class="mt-1 leading-relaxed text-navy-700">{{ $point['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @if ($showcases->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <x-site.section-heading eyebrow="Our work" title="Before & after" />
                <a href="{{ route('work.index') }}" class="inline-flex items-center gap-1 font-semibold text-signal-700 hover:text-navy-900">See all our work <x-heroicon-o-arrow-right class="size-4" aria-hidden="true" /></a>
            </div>
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($showcases as $showcase)
                    <x-site.showcase-card :showcase="$showcase" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="bg-navy-900 text-sand-200">
        <div class="mx-auto grid max-w-6xl items-center gap-8 px-4 py-16 sm:px-6 md:grid-cols-[1fr_auto]">
            <x-site.section-heading :dark="true" eyebrow="Storm season" title="Is your door hurricane-ready?">
                The garage door is often the biggest opening in a house. Six quick questions tell you whether yours is worth a closer look before the next storm.
            </x-site.section-heading>
            <x-site.button :href="route('pages.hurricane-ready')" size="lg">Take the check <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" /></x-site.button>
        </div>
    </section>

    @if ($testimonials->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <x-site.section-heading eyebrow="Reviews" title="From our customers" />
                <a href="{{ route('reviews') }}" class="inline-flex items-center gap-1 font-semibold text-signal-700 hover:text-navy-900">Read more reviews <x-heroicon-o-arrow-right class="size-4" aria-hidden="true" /></a>
            </div>
            <div class="mt-10 grid gap-5 md:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <x-site.testimonial-card :testimonial="$testimonial" />
                @endforeach
            </div>
        </section>
    @endif

    <section @class(['mx-auto max-w-6xl px-4 pb-20 sm:px-6', 'pt-20' => $testimonials->isEmpty()])>
        <x-site.section-heading eyebrow="Where we work" title="From Morehead City to Emerald Isle" />
        <ul class="mt-8 flex flex-wrap gap-3">
            @foreach ($areas as $area)
                <li>
                    <a href="{{ route('areas.show', $area) }}" class="inline-flex items-center gap-2 rounded-full border border-navy-200 bg-white px-4 py-2 font-semibold text-navy-800 transition hover:border-signal-500 hover:text-navy-900">
                        <x-heroicon-o-map-pin class="size-4 text-signal-700" aria-hidden="true" /> {{ $area->name }}
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    <x-site.cta-band :site="$site" />
</x-layouts.site>
