<x-layouts.site :seo="$seo">
    <x-site.page-header :title="$service->headline ?: $service->name" :breadcrumbs="$seo->breadcrumbs" :eyebrow="$service->name">
        {{ $service->summary }}
    </x-site.page-header>

    <div class="mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-12">
        <article class="lg:col-span-8">
            <x-site.prose :html="$service->body" />

            @if ($faqs->isNotEmpty())
                <section class="mt-14">
                    <h2 class="font-display text-3xl font-extrabold tracking-wide uppercase">Common questions</h2>
                    <div class="mt-5 divide-y divide-navy-100 rounded-xl border border-navy-100 bg-white">
                        @foreach ($faqs as $faq)
                            <x-site.faq-item :faq="$faq" />
                        @endforeach
                    </div>
                </section>
            @endif
        </article>

        <aside class="space-y-6 lg:col-span-4">
            <div class="rounded-xl bg-navy-900 p-6 text-sand-200 lg:sticky lg:top-28">
                <h2 class="font-display text-3xl leading-none font-extrabold tracking-wide text-white uppercase">Get a quote</h2>
                <p class="mt-3 leading-relaxed">Tell us what’s going on with your door. Photos help.</p>
                <div class="mt-5 flex flex-col gap-3">
                    <x-site.button :href="route('quote', ['service' => $service->slug])">Request a quote</x-site.button>
                    <x-site.button :href="$site->phoneHref()" variant="outline-light">
                        <x-heroicon-o-phone class="size-5 text-signal-500" aria-hidden="true" /> {{ $site->phoneForDisplay() }}
                    </x-site.button>
                </div>
            </div>
            <nav aria-label="Other services" class="rounded-xl border border-navy-100 bg-white p-6">
                <h2 class="text-sm font-bold tracking-[0.16em] text-navy-600 uppercase">Other services</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($otherServices as $other)
                        <li><a href="{{ route('services.show', $other) }}" class="font-semibold text-navy-800 hover:text-signal-700">{{ $other->name }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </aside>
    </div>

    @if ($showcases->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
            <x-site.section-heading eyebrow="Our work" :title="'Recent '.$service->name.' jobs'" />
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($showcases as $showcase)
                    <x-site.showcase-card :showcase="$showcase" />
                @endforeach
            </div>
        </section>
    @endif

    <x-site.cta-band :site="$site" />
</x-layouts.site>
