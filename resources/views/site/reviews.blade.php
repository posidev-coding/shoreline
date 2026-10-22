<x-layouts.site :seo="$seo">
    <x-site.page-header title="Reviews" :breadcrumbs="$seo->breadcrumbs">
        What customers have said about their doors and the jobs we’ve done for them.
    </x-site.page-header>

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        @if ($testimonials->isEmpty())
            <p class="text-lg text-navy-700">We’re gathering reviews from recent customers. Check back soon.</p>
        @else
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <x-site.testimonial-card :testimonial="$testimonial" />
                @endforeach
            </div>
        @endif

        @if ($site->google_review_url)
            <div class="mt-12 flex flex-col items-start gap-4 rounded-xl bg-navy-900 p-8 text-sand-200 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-display text-3xl font-extrabold tracking-wide text-white uppercase">Worked with us?</h2>
                    <p class="mt-1">A review on Google helps your neighbors find us.</p>
                </div>
                <x-site.button :href="$site->google_review_url" rel="noopener" target="_blank">Leave a Google review</x-site.button>
            </div>
        @endif
    </section>
</x-layouts.site>
