@props(['seo'])
@php
    $title = $seo->fullTitle($site->business_name);
    $primaryNav = [
        ['label' => 'Services', 'route' => 'services.index'],
        ['label' => 'Our Work', 'route' => 'work.index'],
        ['label' => 'Service Areas', 'route' => 'areas.index'],
        ['label' => 'Reviews', 'route' => 'reviews'],
        ['label' => 'Hurricane-Ready', 'route' => 'pages.hurricane-ready'],
        ['label' => 'About', 'route' => 'pages.about'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        <meta name="description" content="{{ $seo->description }}">
        @if ($seo->noindex)
            <meta name="robots" content="noindex">
        @endif
        <link rel="canonical" href="{{ $seo->canonicalUrl() }}">

        <meta property="og:type" content="{{ $seo->type }}">
        <meta property="og:site_name" content="{{ $site->business_name }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $seo->description }}">
        <meta property="og:url" content="{{ $seo->canonicalUrl() }}">
        <meta property="og:image" content="{{ $seo->imageUrl() }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="theme-color" content="#0b1f33">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {!! \App\Support\StructuredData::business($site, $navAreas)->toScript() !!}
        @if ($seo->breadcrumbs !== [])
            {!! \App\Support\StructuredData::breadcrumbs($seo->breadcrumbs)->toScript() !!}
        @endif
        @foreach ($seo->schemas as $schema)
            {!! $schema->toScript() !!}
        @endforeach
        @stack('head')
    </head>
    <body class="flex min-h-screen flex-col bg-salt pb-16 font-sans text-navy-900 md:pb-0">
        <a href="#main" class="sr-only z-50 rounded bg-signal-500 px-4 py-2 font-semibold text-navy-900 focus:not-sr-only focus:fixed focus:top-3 focus:left-3">
            Skip to content
        </a>

        <header class="sticky top-0 z-40 bg-navy-900 text-sand-200 shadow-[0_1px_0_rgb(255_255_255/0.06)]">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-6 px-4 sm:px-6 lg:h-20">
                <a href="{{ route('home') }}" class="shrink-0">
                    <x-site.wordmark id="header" class="h-9 w-auto lg:h-11" />
                </a>

                <nav aria-label="Main" class="hidden lg:block">
                    <ul class="flex items-center gap-6 text-sm font-semibold tracking-wide">
                        @foreach ($primaryNav as $item)
                            @php($isCurrent = request()->routeIs(str_replace('.index', '.*', $item['route'])))
                            <li>
                                <a href="{{ route($item['route']) }}" @if ($isCurrent) aria-current="page" @endif
                                    @class(['transition hover:text-white', 'text-white underline decoration-signal-500 decoration-2 underline-offset-8' => $isCurrent])>{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="hidden items-center gap-5 md:flex">
                    <a href="{{ $site->phoneHref() }}" class="flex items-center gap-2 text-sm font-semibold hover:text-white">
                        <x-heroicon-o-phone class="size-4 text-signal-500" aria-hidden="true" />
                        {{ $site->phoneForDisplay() }}
                    </a>
                    <x-site.button :href="route('quote')" size="sm">Get a quote</x-site.button>
                </div>

                <details class="group lg:hidden">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-md px-2 py-2 text-sm font-semibold [&::-webkit-details-marker]:hidden">
                        <x-heroicon-o-bars-3 class="size-6 group-open:hidden" aria-hidden="true" />
                        <x-heroicon-o-x-mark class="hidden size-6 group-open:block" aria-hidden="true" />
                        <span>Menu</span>
                    </summary>
                    <nav aria-label="Main" class="absolute inset-x-0 top-full border-t border-white/10 bg-navy-900 px-4 pt-2 pb-6 shadow-xl sm:px-6">
                        <ul class="divide-y divide-white/10 text-lg font-semibold">
                            @foreach ([...$primaryNav, ['label' => 'FAQ', 'route' => 'faq'], ['label' => 'Contact', 'route' => 'contact']] as $item)
                                <li><a href="{{ route($item['route']) }}" class="block py-3 hover:text-white">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                </details>
            </div>
        </header>

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <footer class="bg-navy-950 text-sand-200">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-4">
                    <x-site.wordmark id="footer" class="h-10 w-auto" />
                    <p class="text-sm leading-relaxed text-sand-200/80">Garage doors and the odd jobs around the house, from Morehead City to Emerald Isle.</p>
                    <ul class="space-y-2 text-sm">
                        <li class="flex items-start gap-2">
                            <x-heroicon-o-phone class="mt-0.5 size-4 shrink-0 text-signal-500" aria-hidden="true" />
                            <a href="{{ $site->phoneHref() }}" class="hover:text-white">{{ $site->phoneForDisplay() }}</a>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-o-envelope class="mt-0.5 size-4 shrink-0 text-signal-500" aria-hidden="true" />
                            <a href="mailto:{{ $site->email }}" class="hover:text-white">{{ $site->email }}</a>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-o-map-pin class="mt-0.5 size-4 shrink-0 text-signal-500" aria-hidden="true" />
                            <span>
                                @if ($site->street_address){{ $site->street_address }}<br>@endif
                                {{ $site->city }}, {{ $site->region }} {{ $site->postal_code }}
                            </span>
                        </li>
                    </ul>
                    @if ($site->hours !== [])
                        <ul class="space-y-1 text-sm text-sand-200/80">
                            @foreach ($site->hours as $row)
                                <li>{{ \App\Support\Hours::days($row['days']) }}: {{ \App\Support\Hours::time($row['opens']) }}–{{ \App\Support\Hours::time($row['closes']) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div>
                    <h2 class="font-display text-xl font-extrabold tracking-wider text-white uppercase">Services</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($navServices as $service)
                            <li><a href="{{ route('services.show', $service) }}" class="hover:text-white">{{ $service->name }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h2 class="font-display text-xl font-extrabold tracking-wider text-white uppercase">Service areas</h2>
                    <ul class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm lg:grid-cols-1">
                        @foreach ($navAreas as $area)
                            <li><a href="{{ route('areas.show', $area) }}" class="hover:text-white">{{ $area->name }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h2 class="font-display text-xl font-extrabold tracking-wider text-white uppercase">Company</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li><a href="{{ route('pages.about') }}" class="hover:text-white">About</a></li>
                        <li><a href="{{ route('work.index') }}" class="hover:text-white">Our work</a></li>
                        <li><a href="{{ route('reviews') }}" class="hover:text-white">Reviews</a></li>
                        <li><a href="{{ route('faq') }}" class="hover:text-white">FAQ</a></li>
                        <li><a href="{{ route('pages.hurricane-ready') }}" class="hover:text-white">Hurricane-ready check</a></li>
                        <li><a href="{{ route('contact') }}" class="hover:text-white">Contact</a></li>
                        <li><a href="{{ route('quote') }}" class="hover:text-white">Get a quote</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-white/10">
                <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-xs text-sand-200/70 sm:px-6 md:flex-row md:items-center md:justify-between">
                    <p>
                        &copy; {{ now()->year }} {{ $site->business_name }}.
                        @if ($site->license_info) {{ $site->license_info }}. @endif
                        @if ($site->insurance_info) {{ $site->insurance_info }}. @endif
                    </p>
                    <p class="flex gap-4">
                        <a href="{{ route('pages.privacy') }}" class="hover:text-white">Privacy</a>
                        <a href="{{ route('pages.terms') }}" class="hover:text-white">Terms</a>
                    </p>
                </div>
            </div>
        </footer>

        {{-- Sticky action bar on phones: the two things a visitor on a phone usually wants. --}}
        <nav aria-label="Quick actions" class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-2 border-t border-white/10 bg-navy-900 md:hidden">
            <a href="{{ $site->phoneHref() }}" class="flex items-center justify-center gap-2 py-4 font-semibold text-sand-200">
                <x-heroicon-o-phone class="size-5 text-signal-500" aria-hidden="true" />
                Call now
            </a>
            <a href="{{ route('quote') }}" class="flex items-center justify-center gap-2 bg-signal-500 py-4 font-semibold text-navy-900">
                Get a quote
                <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
            </a>
        </nav>
    </body>
</html>
