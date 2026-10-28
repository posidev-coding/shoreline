@php
    /** @var \App\Models\User $user */
    $hasLink = filled($user->calendar_feed_token);
@endphp

<div class="space-y-5 text-sm text-gray-700 dark:text-gray-300">
    <p>
        Your visits can show up in your phone’s calendar, next to everything else. Add this link to Google Calendar,
        Apple Calendar, or Outlook as a subscribed calendar.
    </p>

    @if (filled($url))
        <div x-data="{ copied: false }" class="space-y-2">
            <p class="font-medium text-gray-950 dark:text-white">Your link, shown only this once:</p>
            <div class="flex gap-2">
                <x-filament::input.wrapper class="flex-1">
                    <x-filament::input type="text" readonly :value="$url" x-ref="url" x-on:focus="$event.target.select()" />
                </x-filament::input.wrapper>
                <x-filament::button color="gray" x-on:click="navigator.clipboard.writeText($refs.url.value); copied = true">
                    <span x-show="! copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied</span>
                </x-filament::button>
            </div>
            <x-filament::link :href="preg_replace('/^https?:/', 'webcal:', $url)">
                Open in Apple Calendar (iPhone, iPad, Mac)
            </x-filament::link>
        </div>
    @elseif ($hasLink)
        <p>
            You already have a link.
            @if ($user->calendar_feed_read_at)
                A calendar app last checked it {{ $user->calendar_feed_read_at->diffForHumans() }}.
            @else
                No calendar app has checked it yet.
            @endif
            It’s only shown when it’s made, so to add your visits somewhere else, make a new link and add that one
            everywhere you use it.
        </p>
    @else
        <p>You don’t have a link yet.</p>
    @endif

    <div class="space-y-1">
        <p class="font-medium text-gray-950 dark:text-white">Adding it to Google Calendar</p>
        <p>
            On a computer, open calendar.google.com. Next to “Other calendars”, click +, choose “From URL”, paste the
            link, and click “Add calendar”. It then shows in the Google Calendar app on your phone.
        </p>
        <p>
            Google checks the link only every several hours, so a visit booked this morning may not reach your phone
            until later. The Schedule and the dashboard here are always current.
        </p>
    </div>

    <p class="text-gray-500 dark:text-gray-400">
        Anyone with the link can see your visits: client names, addresses, phone numbers, and visit notes, but never a
        property’s access info. Keep it to yourself. If it gets out, make a new link, and the old one stops working.
    </p>
</div>
