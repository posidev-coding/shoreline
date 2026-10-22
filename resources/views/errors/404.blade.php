@php($seo = new \App\Support\Seo(title: 'Page not found', description: 'That page doesn’t exist.'))
@inject('site', \App\Settings\SiteSettings::class)
<x-layouts.site :seo="$seo">
    <x-site.page-header title="Nothing behind this door">
        That page doesn’t exist, or it moved.
    </x-site.page-header>
    <div class="mx-auto flex max-w-6xl flex-wrap gap-3 px-4 py-16 sm:px-6">
        <x-site.button :href="route('home')" variant="dark">Go to the home page</x-site.button>
        <x-site.button :href="route('services.index')">See our services</x-site.button>
    </div>
</x-layouts.site>
