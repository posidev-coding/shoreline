<x-layouts.site :seo="$seo">
    <x-site.page-header title="Contact" :breadcrumbs="$seo->breadcrumbs">
        The fastest way to reach us is a phone call. For a quote, a few photos of the door help a lot.
    </x-site.page-header>

    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-16 sm:px-6 md:grid-cols-3">
        <a href="{{ $site->phoneHref() }}" class="rounded-xl border border-navy-100 bg-white p-6 transition hover:border-signal-500">
            <x-heroicon-o-phone class="size-8 text-signal-700" aria-hidden="true" />
            <h2 class="mt-4 font-display text-2xl font-extrabold tracking-wide uppercase">Call</h2>
            <p class="mt-1 text-lg font-semibold">{{ $site->phoneForDisplay() }}</p>
        </a>
        <a href="mailto:{{ $site->email }}" class="rounded-xl border border-navy-100 bg-white p-6 transition hover:border-signal-500">
            <x-heroicon-o-envelope class="size-8 text-signal-700" aria-hidden="true" />
            <h2 class="mt-4 font-display text-2xl font-extrabold tracking-wide uppercase">Email</h2>
            <p class="mt-1 text-lg font-semibold break-all">{{ $site->email }}</p>
        </a>
        <div class="rounded-xl border border-navy-100 bg-white p-6">
            <x-heroicon-o-clock class="size-8 text-signal-700" aria-hidden="true" />
            <h2 class="mt-4 font-display text-2xl font-extrabold tracking-wide uppercase">Hours</h2>
            @if ($site->hours === [])
                <p class="mt-1 text-lg">Call to set up a time that works for you.</p>
            @else
                <ul class="mt-1 space-y-1 text-lg">
                    @foreach ($site->hours as $row)
                        <li>{{ \App\Support\Hours::days($row['days']) }}: {{ \App\Support\Hours::time($row['opens']) }}–{{ \App\Support\Hours::time($row['closes']) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
        <h2 class="font-display text-3xl font-extrabold tracking-wide uppercase">Where we work</h2>
        <p class="mt-2 text-lg text-navy-700">
            Based in {{ $site->city }}, {{ $site->region }}. We serve
            {{ $areas->pluck('name')->join(', ', ', and ') }}.
        </p>
    </section>

    <x-site.cta-band :site="$site" />
</x-layouts.site>
