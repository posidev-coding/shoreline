<x-layouts.site :seo="$seo">
    <x-site.page-header :title="$page->title" :breadcrumbs="$seo->breadcrumbs" eyebrow="Storm season">
        {{ $page->intro }}
    </x-site.page-header>

    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <x-site.prose :html="$page->body" />
    </div>

    {{--
        The check is a plain GET form to the quote page, which scores it: no JavaScript, no session,
        and this page stays edge-cacheable. M2's quote wizard reads the same answers as a prefill.
    --}}
    <section id="check" class="bg-navy-900 text-sand-200">
        <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
            <x-site.section-heading :dark="true" eyebrow="Six questions" title="Is your door hurricane-ready?">
                Answer what you can. We’ll tell you whether a closer look is worth it, and you can send us your answers with a quote request.
            </x-site.section-heading>

            <form method="get" action="{{ route('quote') }}" class="mt-10 space-y-8">
                <input type="hidden" name="service" value="hurricane-wind-rated-doors">
                @foreach ($questions as $key => $question)
                    <fieldset>
                        <legend class="text-lg font-bold text-white">{{ $loop->iteration }}. {{ $question['question'] }}</legend>
                        @isset($question['help'])
                            <p class="mt-1 text-sm text-sand-200/80">{{ $question['help'] }}</p>
                        @endisset
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($question['options'] as $value => $option)
                                <label class="cursor-pointer">
                                    <input type="radio" name="{{ $key }}" value="{{ $value }}" class="peer sr-only">
                                    <span class="inline-block rounded-full border border-sand-200/40 px-4 py-2 font-semibold transition peer-checked:border-signal-500 peer-checked:bg-signal-500 peer-checked:text-navy-900 peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-signal-500 hover:border-sand-200">{{ $option['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <div class="border-t border-white/10 pt-8">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-signal-500 px-7 py-4 text-lg font-bold text-navy-900 transition hover:bg-signal-400">
                        See my result <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                    </button>
                    <p class="mt-4 text-sm text-sand-200/80">
                        This check can’t tell you whether a door meets code at your address. Only your local building official can.
                    </p>
                </div>
            </form>
        </div>
    </section>
</x-layouts.site>
