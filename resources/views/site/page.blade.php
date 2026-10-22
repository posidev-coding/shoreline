<x-layouts.site :seo="$seo">
    <x-site.page-header :title="$page->title" :breadcrumbs="$seo->breadcrumbs">
        {{ $page->intro }}
    </x-site.page-header>

    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <x-site.prose :html="$body" />

        @if ($page->key === \App\Enums\PageKey::About && ($site->license_info || $site->insurance_info))
            <ul class="mt-10 space-y-2 rounded-xl border border-navy-100 bg-white p-6">
                @foreach (array_filter([$site->license_info, $site->insurance_info]) as $credential)
                    <li class="flex items-start gap-2 font-semibold"><x-heroicon-o-shield-check class="mt-0.5 size-5 shrink-0 text-signal-700" aria-hidden="true" /> {{ $credential }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($page->key === \App\Enums\PageKey::About)
        <x-site.cta-band :site="$site" />
    @endif
</x-layouts.site>
