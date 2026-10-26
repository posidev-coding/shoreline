<x-layouts.site :seo="$seo">
    <x-site.page-header title="Got it. Thanks!">
        Your request is in, and we’ve emailed you a copy. We’ll get back to you soon.
    </x-site.page-header>

    <div class="mx-auto max-w-3xl space-y-6 px-4 py-16 text-lg leading-relaxed text-navy-800 sm:px-6">
        @unless ($site->hasPlaceholderPhone())
            <p>If your door is stuck or unsafe right now, don’t wait for us: call <a href="{{ $site->phoneHref() }}" class="font-semibold text-signal-700 underline underline-offset-2">{{ $site->phoneForDisplay() }}</a>.</p>
        @endunless
        <p>In the meantime, see <a href="{{ route('work.index') }}" class="font-semibold text-signal-700 underline underline-offset-2">some of our recent work</a>, or read the <a href="{{ route('faq') }}" class="font-semibold text-signal-700 underline underline-offset-2">answers to common questions</a>.</p>
    </div>
</x-layouts.site>
