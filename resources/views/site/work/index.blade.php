<x-layouts.site :seo="$seo">
    <x-site.page-header title="Our Work" :breadcrumbs="$seo->breadcrumbs">
        Doors we’ve installed and repaired around the Crystal Coast. Open one and drag to compare before and after.
    </x-site.page-header>

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <form method="get" action="{{ route('work.index') }}" class="flex flex-wrap items-end gap-4 rounded-xl border border-navy-100 bg-white p-5">
            <label class="flex min-w-48 flex-1 flex-col gap-1 text-sm font-semibold text-navy-800">
                Service
                <select name="service" class="rounded-md border border-navy-200 bg-white px-3 py-2 text-base font-normal">
                    <option value="">All services</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->slug }}" @selected($selectedService?->is($service))>{{ $service->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex min-w-48 flex-1 flex-col gap-1 text-sm font-semibold text-navy-800">
                Town
                <select name="town" class="rounded-md border border-navy-200 bg-white px-3 py-2 text-base font-normal">
                    <option value="">All towns</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->slug }}" @selected($selectedArea?->is($area))>{{ $area->name }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="rounded-md bg-navy-900 px-6 py-2.5 font-bold text-sand-100 hover:bg-navy-800">Filter</button>
            @if ($selectedService || $selectedArea)
                <a href="{{ route('work.index') }}" class="py-2.5 font-semibold text-signal-700 hover:text-navy-900">Clear</a>
            @endif
        </form>

        @if ($showcases->isEmpty())
            <p class="mt-12 text-lg text-navy-700">
                @if ($selectedService || $selectedArea)
                    Nothing matches that filter yet. <a href="{{ route('work.index') }}" class="font-semibold text-signal-700 underline">See all our work</a>.
                @else
                    We’re adding photos from recent jobs. Check back soon, or <a href="{{ route('quote') }}" class="font-semibold text-signal-700 underline">ask about yours</a>.
                @endif
            </p>
        @else
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($showcases as $showcase)
                    <x-site.showcase-card :showcase="$showcase" />
                @endforeach
            </div>
            <div class="mt-10">{{ $showcases->links() }}</div>
        @endif
    </section>

    <x-site.cta-band :site="$site" />
</x-layouts.site>
