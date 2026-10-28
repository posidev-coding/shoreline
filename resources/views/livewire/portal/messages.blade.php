@php
    $timezone = config('app.business_timezone');
    $input = 'block w-full rounded-md border border-navy-200 bg-white px-4 py-3 text-navy-900 shadow-xs placeholder:text-navy-300 focus:border-signal-500 focus:ring-2 focus:ring-signal-500/40 focus:outline-none';
@endphp

<section id="messages" aria-labelledby="messages-heading" class="mt-10 scroll-mt-6">
    <h2 id="messages-heading" class="font-display text-2xl font-extrabold tracking-wide text-navy-900 uppercase">Messages</h2>

    @if ($messages->isEmpty())
        <p class="mt-4 text-navy-700">No messages yet.@if ($canPost) Questions about this job? Send us one, and we’ll get back to you.@endif</p>
    @else
        <ol class="mt-4 space-y-3">
            @foreach ($messages as $message)
                <li wire:key="message-{{ $message->getKey() }}" @class([
                    'rounded-lg p-5 ring-1',
                    'bg-white ring-navy-100 sm:me-12' => ! $message->is_from_client,
                    'bg-navy-50 ring-navy-100 sm:ms-12' => $message->is_from_client,
                ])>
                    <p class="text-sm text-navy-700">
                        <span class="font-semibold text-navy-900">{{ $fromName($message) }}</span>
                        · <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->timezone($timezone)->format('D, M j · g:i A') }}</time>
                    </p>
                    <p class="mt-1 break-words whitespace-pre-line text-navy-900">{{ $message->body }}</p>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($canPost)
        <form wire:submit="send" class="mt-6 space-y-4" novalidate>
            <x-site.field for="message-body" label="Send a message" :error="$errors->first('body')">
                <textarea id="message-body" wire:model="body" rows="4" maxlength="{{ \App\Support\MessageThread::MAX_LENGTH }}" class="{{ $input }}" @error('body') aria-invalid="true" aria-describedby="message-body-error" @enderror></textarea>
            </x-site.field>

            <div class="flex flex-wrap items-center gap-4">
                <button type="submit" wire:loading.attr="disabled" wire:target="send" class="inline-flex items-center justify-center gap-2 rounded-md bg-signal-500 px-6 py-3 text-base font-bold tracking-wide text-navy-900 transition hover:bg-signal-400 disabled:opacity-60">
                    <x-heroicon-o-paper-airplane class="size-5" aria-hidden="true" />
                    Send
                </button>
                @if ($sent)
                    <p class="text-sm font-semibold text-navy-800" role="status">Sent. We’ll get back to you soon.</p>
                @endif
            </div>
        </form>
    @elseif ($isImpersonating)
        <p class="mt-6 rounded-lg bg-white p-5 text-navy-700 ring-1 ring-navy-100">You’re viewing the portal as {{ $user->name }}, so you can’t send from here. Reply on the job in the admin.</p>
    @else
        <p class="mt-6 rounded-lg bg-white p-5 text-navy-700 ring-1 ring-navy-100">You can read messages here, but your access doesn’t include replying. Questions? Give us a call.</p>
    @endif
</section>
