@php
    $input = 'block w-full rounded-md border border-navy-200 bg-white px-4 py-3 text-navy-900 shadow-xs placeholder:text-navy-300 focus:border-signal-500 focus:ring-2 focus:ring-signal-500/40 focus:outline-none';
    $button = 'inline-flex w-full items-center justify-center gap-2 rounded-md bg-signal-500 px-6 py-3 text-base font-bold tracking-wide text-navy-900 transition hover:bg-signal-400 disabled:opacity-60';
@endphp

<div>
    @if (! $codeSent)
        <form wire:submit="sendCode" class="space-y-6" novalidate>
            <x-site.field for="email" label="Email" hint="The address we have for you. We’ll email you a code." :error="$errors->first('email')">
                <input id="email" type="email" wire:model="email" autocomplete="email" inputmode="email" autofocus class="{{ $input }}" aria-describedby="email-hint" @error('email') aria-invalid="true" @enderror>
            </x-site.field>

            <button type="submit" wire:loading.attr="disabled" wire:target="sendCode" class="{{ $button }}">
                Email me a code
            </button>
        </form>
    @else
        <form wire:submit="signIn" class="space-y-6" novalidate>
            <p class="text-navy-800" role="status">
                If <strong class="break-all">{{ $email }}</strong> has a portal, we’ve emailed it a 6-digit code. It works for {{ \App\Support\PortalSignIn::CODE_MINUTES }} minutes.
            </p>

            <x-site.field for="code" label="Code" :error="$errors->first('code')">
                <input id="code" type="text" wire:model="code" autocomplete="one-time-code" inputmode="numeric" maxlength="7" autofocus class="{{ $input }} text-center font-mono text-2xl tracking-[0.4em]" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
            </x-site.field>

            <button type="submit" wire:loading.attr="disabled" wire:target="signIn" class="{{ $button }}">
                Sign in
            </button>

            <div class="flex flex-wrap justify-between gap-3 text-sm">
                <button type="button" wire:click="sendCode" class="font-semibold text-signal-700 underline underline-offset-2 hover:text-navy-900">Send a new code</button>
                <button type="button" wire:click="useAnotherEmail" class="font-semibold text-navy-700 underline underline-offset-2 hover:text-navy-900">Use another email</button>
            </div>
        </form>
    @endif
</div>
