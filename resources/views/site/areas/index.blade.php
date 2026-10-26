<x-layouts.site :seo="$seo">
    <x-site.page-header title="Service Areas" :breadcrumbs="$seo->breadcrumbs">
        Based in Morehead City, we work across Carteret County, plus Swansboro in Onslow County and Havelock in Craven County.
    </x-site.page-header>

    <section class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:grid-cols-3">
        @foreach ($areasByCounty as $county => $areas)
            <div>
                <h2 class="font-display text-3xl font-extrabold tracking-wide uppercase">{{ $county }}</h2>
                <ul class="mt-4 space-y-2">
                    @foreach ($areas as $area)
                        <li>
                            <a href="{{ route('areas.show', $area) }}" class="group flex items-center justify-between rounded-lg border border-navy-100 bg-white px-4 py-3 font-semibold text-navy-800 transition hover:border-signal-500">
                                {{ $area->name }}
                                <x-heroicon-o-arrow-right class="size-4 text-signal-700 transition group-hover:translate-x-0.5" aria-hidden="true" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </section>

    <x-site.cta-band :site="$site" title="Not sure if we cover you?">
        Call or send a quote request with your town. If we can’t help, we’ll say so.
    </x-site.cta-band>
</x-layouts.site>
