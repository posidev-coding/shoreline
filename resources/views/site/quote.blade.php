<x-layouts.site :seo="$seo">
    <x-site.page-header title="Get a Quote" :breadcrumbs="$seo->breadcrumbs" :eyebrow="$service?->name">
        Tell us about the door or the job, and where it is. We’ll get back to you with next steps.
    </x-site.page-header>

    <div class="mx-auto max-w-3xl space-y-10 px-4 py-16 sm:px-6">
        @if ($readiness->hasResult())
            <section @class([
                'rounded-xl border-l-8 bg-white p-6 shadow-sm',
                'border-emerald-600' => $readiness->level() === 'ready',
                'border-signal-500' => $readiness->level() === 'check',
                'border-red-700' => $readiness->level() === 'inspect',
            ])>
                <p class="text-sm font-bold tracking-[0.16em] text-navy-600 uppercase">Your hurricane-ready check</p>
                <h2 class="mt-2 font-display text-3xl font-extrabold tracking-wide uppercase">{{ $readiness->headline() }}</h2>
                <p class="mt-2 text-lg leading-relaxed text-navy-700">{{ $readiness->advice() }}</p>
                <p class="mt-4 text-sm text-navy-600">Whether a door meets code at your address is up to your local building official. This check only tells you whether a closer look is worth it.</p>
            </section>
        @endif

        <section class="rounded-xl bg-navy-900 p-8 text-sand-200">
            <h2 class="font-display text-3xl font-extrabold tracking-wide text-white uppercase">Request a quote</h2>
            <p class="mt-2 leading-relaxed">
                Online quote requests with photos are on the way. For now, call, or send an email.
                {{ $readiness->hasResult() ? 'We’ve started it for you with your answers.' : 'We’ve started it for you.' }}
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <x-site.button :href="$mailto" size="lg"><x-heroicon-o-envelope class="size-5" aria-hidden="true" /> Email us</x-site.button>
                <x-site.button :href="$site->phoneHref()" variant="outline-light" size="lg">
                    <x-heroicon-o-phone class="size-5 text-signal-500" aria-hidden="true" /> {{ $site->phoneForDisplay() }}
                </x-site.button>
            </div>
        </section>
    </div>
</x-layouts.site>
