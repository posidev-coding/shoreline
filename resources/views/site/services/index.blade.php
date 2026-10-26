<x-layouts.site :seo="$seo">
    <x-site.page-header title="Services" :breadcrumbs="$seo->breadcrumbs">
        Garage doors are the core of the business, from new installs to the spring that snapped this morning. We take on the odd jobs around the house too.
    </x-site.page-header>

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($services as $service)
                <x-site.service-card :service="$service" />
            @endforeach
        </div>
    </section>

    <x-site.cta-band :site="$site" />
</x-layouts.site>
