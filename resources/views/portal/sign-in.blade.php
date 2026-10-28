<x-layouts.portal title="Sign in">
    <div class="mx-auto max-w-md">
        <h1 class="font-display text-4xl font-extrabold tracking-wide text-navy-900 uppercase">Your projects</h1>
        <p class="mt-2 text-navy-700">Sign in to see your projects with {{ $site->business_name }} and when we’re coming out.</p>

        @if (session('status'))
            <p role="status" class="mt-6 rounded-md bg-sand-100 px-4 py-3 text-navy-800 ring-1 ring-sand-300">{{ session('status') }}</p>
        @endif

        <div class="mt-8 rounded-lg bg-white p-6 shadow-sm ring-1 ring-navy-100">
            <livewire:portal.sign-in :email="$email" />
        </div>

        <p class="mt-6 text-sm text-navy-700">
            The portal opens once we’re working on a job for you, and we email you an invitation.
            Need work done? <a href="{{ route('quote') }}" class="font-semibold text-signal-700 underline underline-offset-2 hover:text-navy-900">Get a quote</a>.
        </p>
    </div>
</x-layouts.portal>
