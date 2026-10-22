<x-layouts.site :seo="$seo">
    <x-site.page-header title="FAQ" :breadcrumbs="$seo->breadcrumbs">
        Straight answers to the questions we hear most. Don’t see yours? Give us a call.
    </x-site.page-header>

    <div class="mx-auto max-w-3xl space-y-14 px-4 py-16 sm:px-6">
        @if ($generalFaqs->isNotEmpty())
            <section>
                <h2 class="sr-only">General questions</h2>
                <div class="divide-y divide-navy-100 rounded-xl border border-navy-100 bg-white">
                    @foreach ($generalFaqs as $faq)
                        <x-site.faq-item :faq="$faq" />
                    @endforeach
                </div>
            </section>
        @endif

        @foreach ($services as $service)
            <section>
                <h2 class="font-display text-3xl font-extrabold tracking-wide uppercase">
                    <a href="{{ route('services.show', $service) }}" class="hover:text-signal-700">{{ $service->name }}</a>
                </h2>
                <div class="mt-4 divide-y divide-navy-100 rounded-xl border border-navy-100 bg-white">
                    @foreach ($service->faqs as $faq)
                        <x-site.faq-item :faq="$faq" />
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <x-site.cta-band :site="$site" title="Still have a question?" />
</x-layouts.site>
