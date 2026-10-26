<x-layouts.site :seo="$seo">
    <x-site.page-header :title="'Garage doors in '.$area->name" :breadcrumbs="$seo->breadcrumbs" :eyebrow="$area->county->getLabel().', NC'" />

    <div class="mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <x-site.prose :html="$area->intro" />
        </div>
        <aside class="lg:col-span-4">
            <div class="rounded-xl border border-navy-100 bg-white p-6">
                <h2 class="text-sm font-bold tracking-[0.16em] text-navy-600 uppercase">What we do in {{ $area->name }}</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($services as $service)
                        <li>
                            <a href="{{ route('services.show', $service) }}" class="flex items-center gap-3 font-semibold text-navy-800 hover:text-signal-700">
                                <x-site.icon :name="$service->icon" class="size-5 text-signal-700" /> {{ $service->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </div>

    @if ($showcases->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
            <x-site.section-heading eyebrow="Our work" :title="'Jobs in '.$area->name" />
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($showcases as $showcase)
                    <x-site.showcase-card :showcase="$showcase" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($testimonials->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
            <x-site.section-heading eyebrow="Reviews" :title="'From '.$area->name" />
            <div class="mt-8 grid gap-5 md:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <x-site.testimonial-card :testimonial="$testimonial" />
                @endforeach
            </div>
        </section>
    @endif

    <x-site.cta-band :site="$site" :title="'Need a door looked at in '.$area->name.'?'" />
</x-layouts.site>
