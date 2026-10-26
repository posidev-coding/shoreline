<x-layouts.site :seo="$seo">
    @if (\App\Livewire\QuoteWizard::usesTurnstile())
        @push('head')
            {{-- Explicit rendering: the wizard renders the widget once Alpine and this script are both ready. --}}
            <script>window.onTurnstileReady = () => window.dispatchEvent(new Event('turnstile:ready'))</script>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onTurnstileReady" async defer></script>
        @endpush
    @endif

    <x-site.page-header title="Get a Quote" :breadcrumbs="$seo->breadcrumbs" :eyebrow="$service?->name">
        Tell us what you need and where, add a few photos if you can, and we’ll get back to you.
    </x-site.page-header>

    <div class="mx-auto max-w-3xl space-y-10 px-4 py-12 sm:px-6 sm:py-16">
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

        <livewire:quote-wizard :service-slug="$service?->slug" :quiz-answers="$readiness->answers" />

        @unless ($site->hasPlaceholderPhone())
            <p class="text-center text-navy-700">
                Rather talk it through? Call <a href="{{ $site->phoneHref() }}" class="font-semibold text-signal-700 underline underline-offset-2">{{ $site->phoneForDisplay() }}</a>.
            </p>
        @endunless
    </div>
</x-layouts.site>
