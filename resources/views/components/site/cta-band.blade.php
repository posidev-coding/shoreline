@props(['title' => 'Need a door looked at?', 'site'])
<section class="bg-signal-500">
    <div class="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-12 sm:px-6 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="font-display text-4xl leading-none font-extrabold tracking-wide text-navy-900 uppercase sm:text-5xl">{{ $title }}</h2>
            <p class="mt-2 text-lg text-navy-900">{{ $slot->isNotEmpty() ? $slot : 'Tell us what’s going on and we’ll get back to you with next steps.' }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <x-site.button :href="route('quote')" variant="dark" size="lg">Get a quote</x-site.button>
            <a href="{{ $site->phoneHref() }}" class="inline-flex items-center gap-2 rounded-md px-5 py-4 text-lg font-bold text-navy-900 ring-2 ring-inset ring-navy-900 transition hover:bg-navy-900 hover:text-sand-100">
                <x-heroicon-o-phone class="size-5" aria-hidden="true" /> {{ $site->phoneForDisplay() }}
            </a>
        </div>
    </div>
</section>
