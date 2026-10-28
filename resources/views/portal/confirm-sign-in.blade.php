<x-layouts.portal title="Sign in">
    <div class="mx-auto max-w-md text-center">
        <h1 class="font-display text-4xl font-extrabold tracking-wide text-navy-900 uppercase">Sign in</h1>
        <p class="mt-3 text-navy-700">
            Sign in to your projects as <strong class="break-all text-navy-900">{{ $email }}</strong>?
        </p>

        {{-- A button, not an automatic sign-in: mail scanners open links, and would use the code up. --}}
        <form method="POST" action="{{ route('portal.sign-in.store') }}" class="mt-8">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <input type="hidden" name="code" value="{{ $code }}">
            <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-signal-500 px-6 py-3 text-base font-bold tracking-wide text-navy-900 transition hover:bg-signal-400">
                Sign in
            </button>
        </form>

        <p class="mt-6 text-sm text-navy-700">
            Not you? <a href="{{ route('portal.login') }}" class="font-semibold text-signal-700 underline underline-offset-2 hover:text-navy-900">Sign in with another email</a>.
        </p>
    </div>
</x-layouts.portal>
