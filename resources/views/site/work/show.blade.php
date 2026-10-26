<x-layouts.site :seo="$seo">
    <x-site.page-header :title="$showcase->title" :breadcrumbs="$seo->breadcrumbs"
        :eyebrow="collect([$showcase->service?->name, $showcase->serviceArea ? $showcase->serviceArea->name.', NC' : null])->filter()->implode(' · ')" />

    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
        <x-site.before-after :before="$showcase->getFirstMedia('before')" :after="$showcase->getFirstMedia('after')" :title="$showcase->title" />

        @if ($showcase->story)
            <x-site.prose :html="$showcase->story" class="mx-auto mt-12 max-w-3xl" />
        @endif

        <p class="mx-auto mt-10 flex max-w-3xl flex-wrap gap-x-6 gap-y-2 text-sm font-semibold">
            @if ($showcase->service)
                <a href="{{ route('services.show', $showcase->service) }}" class="text-signal-700 hover:text-navy-900">More about {{ $showcase->service->name }}</a>
            @endif
            @if ($showcase->serviceArea)
                <a href="{{ route('areas.show', $showcase->serviceArea) }}" class="text-signal-700 hover:text-navy-900">Our work in {{ $showcase->serviceArea->name }}</a>
            @endif
        </p>
    </div>

    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
            <x-site.section-heading title="More of our work" />
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    <x-site.showcase-card :showcase="$item" />
                @endforeach
            </div>
        </section>
    @endif

    <x-site.cta-band :site="$site" />
</x-layouts.site>
