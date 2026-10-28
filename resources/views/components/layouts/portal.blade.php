@props(['title'])
@php($user = auth()->user())
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · {{ $site->business_name }}</title>
        <meta name="robots" content="noindex">
        <meta name="theme-color" content="#0b1f33">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-salt font-sans text-navy-900">
        <a href="#main" class="sr-only z-50 rounded bg-signal-500 px-4 py-2 font-semibold text-navy-900 focus:not-sr-only focus:fixed focus:top-3 focus:left-3">
            Skip to content
        </a>

        <header class="bg-navy-900 text-sand-200">
            <div class="mx-auto flex h-16 max-w-3xl items-center justify-between gap-4 px-4 sm:px-6">
                <a href="{{ $user ? route('portal.dashboard') : route('home') }}" class="shrink-0">
                    <x-site.wordmark id="portal-header" class="h-8 w-auto" />
                </a>

                @if ($user)
                    <div class="flex items-center gap-4 text-sm">
                        <a href="{{ route('portal.dashboard') }}" class="font-semibold hover:text-white">Your projects</a>
                        {{-- An admin viewing someone's portal leaves through the banner; signing out here would end the admin's own session. --}}
                        @unless (\STS\FilamentImpersonate\Facades\Impersonation::isImpersonating())
                            <form method="POST" action="{{ route('portal.logout') }}">
                                @csrf
                                <button type="submit" class="font-semibold text-sand-200/80 hover:text-white">Sign out</button>
                            </form>
                        @endunless
                    </div>
                @endif
            </div>
        </header>

        <main id="main" class="mx-auto w-full max-w-3xl flex-1 px-4 py-10 sm:px-6">
            {{ $slot }}
        </main>

        <footer class="border-t border-navy-100 bg-white">
            <div class="mx-auto flex max-w-3xl flex-col gap-2 px-4 py-6 text-sm text-navy-700 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p>
                    Questions?
                    @if (! $site->hasPlaceholderPhone())
                        Call <a href="{{ $site->phoneHref() }}" class="font-semibold text-signal-700 underline underline-offset-2 hover:text-navy-900">{{ $site->phoneForDisplay() }}</a>
                        or email
                    @else
                        Email
                    @endif
                    <a href="mailto:{{ $site->email }}" class="font-semibold text-signal-700 underline underline-offset-2 hover:text-navy-900">{{ $site->email }}</a>.
                </p>
                <a href="{{ route('home') }}" class="hover:text-navy-900">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</a>
            </div>
        </footer>

        <x-impersonate::banner />
    </body>
</html>
