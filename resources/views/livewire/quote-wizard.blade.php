@use('App\Enums\ContactMethod')
@use('App\Enums\Urgency')
@use('App\Livewire\QuoteWizard')
@use('App\Models\Lead')
@use('App\Support\HurricaneReadiness')
@use('App\Support\QuoteQuestions')
@use('Illuminate\Support\Str')
@php
    $selected = $this->selectedService;
    $input = 'block w-full rounded-md border border-navy-200 bg-white px-4 py-3 text-navy-900 shadow-xs placeholder:text-navy-300 focus:border-signal-500 focus:ring-2 focus:ring-signal-500/40 focus:outline-none';
    $pill = 'inline-block cursor-pointer rounded-full border border-navy-200 bg-white px-4 py-2 font-semibold text-navy-800 transition peer-checked:border-signal-500 peer-checked:bg-signal-500 peer-checked:text-navy-900 peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-signal-500 hover:border-navy-400';
@endphp

<div
    class="rounded-2xl bg-white shadow-sm ring-1 ring-navy-100"
    x-data
    x-init="$wire.attribution = window.storedAttribution()"
>
    {{-- Steps --}}
    <nav aria-label="Quote request steps" class="border-b border-navy-100 px-4 py-4 sm:px-8">
        <ol class="flex gap-1 overflow-x-auto text-sm">
            @foreach (QuoteWizard::STEPS as $number => $label)
                <li class="flex-1">
                    @if ($number <= $furthestStep && $number !== $step)
                        <button type="button" wire:click="goTo({{ $number }})" class="w-full border-t-4 border-navy-700 pt-2 text-left font-semibold whitespace-nowrap text-navy-700 hover:text-navy-900">
                            <span class="sr-only">Step</span> {{ $number }}<span class="max-sm:sr-only">. {{ $label }}</span>
                        </button>
                    @else
                        <span @class([
                            'block w-full border-t-4 pt-2 font-semibold whitespace-nowrap',
                            'border-signal-500 text-navy-900' => $number === $step,
                            'border-navy-100 text-navy-300' => $number !== $step,
                        ]) @if ($number === $step) aria-current="step" @endif>
                            {{-- On a phone only the current step is named; the rest are numbers. --}}
                            <span class="sr-only">Step</span> {{ $number }}<span @class(['max-sm:sr-only' => $number !== $step])>. {{ $label }}</span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    <form wire:submit="{{ $step === QuoteWizard::REVIEW ? 'submit' : 'next' }}" class="space-y-8 px-4 py-8 sm:px-8" novalidate>
        <x-honeypot livewire-model="extraFields" />

        @if ($step === QuoteWizard::SERVICE)
            <fieldset>
                <legend class="font-display text-3xl font-extrabold tracking-wide text-navy-900 uppercase">What do you need?</legend>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    @foreach ($this->services as $option)
                        <label class="relative block cursor-pointer">
                            <input type="radio" wire:model="service" value="{{ $option->slug }}" class="peer sr-only">
                            <span class="flex h-full items-start gap-4 rounded-xl border-2 border-navy-100 p-4 transition peer-checked:border-signal-500 peer-checked:bg-signal-500/5 peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-signal-500 hover:border-navy-300">
                                <x-site.icon :name="$option->icon" class="size-8 shrink-0 text-signal-700" />
                                <span>
                                    <span class="block font-bold text-navy-900">{{ $option->name }}</span>
                                    <span class="mt-1 block text-sm text-navy-600">{{ $option->summary }}</span>
                                </span>
                            </span>
                        </label>
                    @endforeach
                    <label class="relative block cursor-pointer">
                        <input type="radio" wire:model="service" value="other" class="peer sr-only">
                        <span class="flex h-full items-start gap-4 rounded-xl border-2 border-navy-100 p-4 transition peer-checked:border-signal-500 peer-checked:bg-signal-500/5 peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-signal-500 hover:border-navy-300">
                            <x-heroicon-o-question-mark-circle class="size-8 shrink-0 text-signal-700" aria-hidden="true" />
                            <span>
                                <span class="block font-bold text-navy-900">Something else</span>
                                <span class="mt-1 block text-sm text-navy-600">Tell us about it on the next step.</span>
                            </span>
                        </span>
                    </label>
                </div>
                @error('service') <p class="mt-3 text-sm font-semibold text-red-700">Choose what you need, or “Something else”.</p> @enderror
            </fieldset>
        @endif

        @if ($step === QuoteWizard::DETAILS)
            <div class="space-y-8">
                <h2 class="font-display text-3xl font-extrabold tracking-wide text-navy-900 uppercase">{{ $selected->name ?? 'Tell us about the job' }}</h2>

                @if ($hurricane !== [])
                    <p class="rounded-lg bg-sand-100 px-4 py-3 text-sm text-navy-800">
                        Your hurricane-ready answers come with this request: {{ HurricaneReadiness::fromInput($hurricane)->headline() }}
                    </p>
                @endif

                @if (QuoteQuestions::asksDoorSize($selected))
                    <fieldset>
                        <legend class="font-semibold text-navy-900">Which door?</legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (QuoteQuestions::DOOR_SIZES as $value => $label)
                                <label><input type="radio" wire:model="doorSize" value="{{ $value }}" class="peer sr-only"><span class="{{ $pill }}">{{ $label }}</span></label>
                            @endforeach
                        </div>
                        @error('doorSize') <p class="mt-2 text-sm font-semibold text-red-700">Pick one, or “Not sure”.</p> @enderror
                    </fieldset>
                @endif

                @if (QuoteQuestions::asksStyle($selected))
                    <fieldset>
                        <legend class="font-semibold text-navy-900">Style you’re after <span class="font-normal text-navy-600">(optional)</span></legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (QuoteQuestions::STYLES as $value => $label)
                                <label><input type="radio" wire:model="style" value="{{ $value }}" class="peer sr-only"><span class="{{ $pill }}">{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                @if (QuoteQuestions::asksSymptoms($selected))
                    <fieldset>
                        <legend class="font-semibold text-navy-900">What’s going on? <span class="font-normal text-navy-600">(pick any)</span></legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (QuoteQuestions::SYMPTOMS as $value => $label)
                                <label><input type="checkbox" wire:model="symptoms" value="{{ $value }}" class="peer sr-only"><span class="{{ $pill }}">{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <fieldset>
                    <legend class="font-semibold text-navy-900">How soon?</legend>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (Urgency::cases() as $option)
                            <label><input type="radio" wire:model="urgency" value="{{ $option->value }}" class="peer sr-only"><span class="{{ $pill }}">{{ $option->getLabel() }}</span></label>
                        @endforeach
                    </div>
                    @error('urgency') <p class="mt-2 text-sm font-semibold text-red-700">Let us know how soon you need us.</p> @enderror
                </fieldset>

                <x-site.field
                    for="message"
                    :label="QuoteQuestions::requiresMessage($selected) ? 'Describe the job' : 'Anything else we should know?'"
                    :optional="! QuoteQuestions::requiresMessage($selected)"
                    :error="$errors->first('message')"
                >
                    <textarea id="message" wire:model="message" rows="4" maxlength="2000" class="{{ $input }}" @error('message') aria-invalid="true" aria-describedby="message-error" @enderror></textarea>
                </x-site.field>
            </div>
        @endif

        @if ($step === QuoteWizard::PHOTOS)
            <div class="space-y-6">
                <div>
                    <h2 class="font-display text-3xl font-extrabold tracking-wide text-navy-900 uppercase">Photos help</h2>
                    <p class="mt-2 text-navy-700">Optional, but they often save a trip. Up to {{ Lead::MAX_PHOTOS }}: the whole door from outside, the springs above it, the opener, and anything broken.</p>
                </div>

                {{--
                    One upload per photo, in turn. Livewire uploads to a bucket (as on Laravel Cloud) only one file at a
                    time, and its own queue starts the last waiting upload rather than the next, so this keeps the order.
                    A single upload to an array property is appended to it, which runs QuoteWizard::updatedPhotos().
                --}}
                <div
                    x-data="{
                        uploading: false,
                        progress: 0,
                        current: 0,
                        total: 0,
                        skipped: 0,
                        async send(files) {
                            const room = Math.max({{ Lead::MAX_PHOTOS }} - $wire.photos.length, 0);
                            const queue = Array.from(files).slice(0, room);
                            this.skipped = files.length - queue.length;
                            this.total = queue.length;
                            this.uploading = queue.length > 0;

                            for (const [index, file] of queue.entries()) {
                                this.current = index + 1;
                                this.progress = 0;
                                await new Promise((done) => $wire.upload('photos', file, done, done, (event) => this.progress = event.detail.progress, done));
                            }

                            this.uploading = false;
                        },
                    }"
                >
                    @if (count($photos) < Lead::MAX_PHOTOS)
                        <input id="photos" type="file" multiple accept="image/*,.heic,.heif" class="peer sr-only" x-on:change="send($event.target.files); $event.target.value = ''" x-bind:disabled="uploading">
                        <label for="photos" class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-navy-900 px-6 py-3 font-bold tracking-wide text-sand-100 transition peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-signal-500 hover:bg-navy-800">
                            <x-heroicon-o-camera class="size-5" aria-hidden="true" /> {{ count($photos) === 0 ? 'Add photos' : 'Add more photos' }}
                        </label>
                    @endif

                    <div x-show="uploading" x-cloak class="mt-4" role="status">
                        <p class="text-sm font-semibold text-navy-700">Uploading photo <span x-text="current"></span> of <span x-text="total"></span>… <span x-text="progress + '%'"></span></p>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-navy-100"><div class="h-full bg-signal-500 transition-all" :style="`width: ${progress}%`"></div></div>
                    </div>

                    <p x-show="skipped > 0" x-cloak class="mt-3 text-sm font-semibold text-red-700">Up to {{ Lead::MAX_PHOTOS }} photos, so <span x-text="skipped"></span> of them didn’t fit.</p>
                    @error('photos') <p class="mt-3 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    @error('photos.*') <p class="mt-3 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror

                    @if ($photos !== [])
                        <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($photos as $index => $photo)
                                <li wire:key="photo-{{ $photo->getFilename() }}" class="relative overflow-hidden rounded-lg ring-1 ring-navy-100">
                                    @if ($photo->isPreviewable())
                                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="aspect-4/3 w-full object-cover">
                                    @else
                                        {{-- HEIC: most browsers can't preview it, but it uploads fine. --}}
                                        <span class="flex aspect-4/3 w-full flex-col items-center justify-center gap-1 bg-sand-50 p-2 text-center text-xs text-navy-700">
                                            <x-heroicon-o-photo class="size-6" aria-hidden="true" /> {{ Str::limit($photo->getClientOriginalName(), 24) }}
                                        </span>
                                    @endif
                                    <button type="button" wire:click="removePhoto({{ $index }})" class="absolute top-2 right-2 rounded-full bg-navy-900/80 p-1.5 text-white hover:bg-navy-900">
                                        <x-heroicon-o-x-mark class="size-4" aria-hidden="true" /><span class="sr-only">Remove {{ $photo->getClientOriginalName() }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        @if ($step === QuoteWizard::CONTACT)
            <div class="space-y-6">
                <h2 class="font-display text-3xl font-extrabold tracking-wide text-navy-900 uppercase">Where and who</h2>

                <div class="grid gap-6 sm:grid-cols-2">
                    <x-site.field for="name" label="Your name" :error="$errors->first('name')" class="sm:col-span-2">
                        <input id="name" type="text" wire:model="name" autocomplete="name" class="{{ $input }}" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    </x-site.field>
                    <x-site.field for="phone" label="Phone" :error="$errors->first('phone') ? 'Enter a US phone number.' : null">
                        <input id="phone" type="tel" wire:model="phone" autocomplete="tel" inputmode="tel" class="{{ $input }}" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                    </x-site.field>
                    <x-site.field for="email" label="Email" :error="$errors->first('email')">
                        <input id="email" type="email" wire:model="email" autocomplete="email" class="{{ $input }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    </x-site.field>
                </div>

                <fieldset>
                    <legend class="font-semibold text-navy-900">Best way to reach you</legend>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (ContactMethod::cases() as $option)
                            <label><input type="radio" wire:model="preferredContact" value="{{ $option->value }}" class="peer sr-only"><span class="{{ $pill }}">{{ $option->getLabel() }}</span></label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid gap-6 sm:grid-cols-6">
                    <x-site.field for="street" label="Street address" hint="Where the work is. It stays private." :error="$errors->first('street')" class="sm:col-span-6">
                        <input id="street" type="text" wire:model="street" autocomplete="address-line1" aria-describedby="street-hint" class="{{ $input }}" @error('street') aria-invalid="true" @enderror>
                    </x-site.field>
                    <x-site.field for="town" label="Town" :error="$errors->first('town') ? 'Choose your town.' : null" class="sm:col-span-4">
                        <select id="town" wire:model="town" class="{{ $input }}" @error('town') aria-invalid="true" aria-describedby="town-error" @enderror>
                            <option value="">Choose…</option>
                            @foreach ($this->towns as $area)
                                <option value="{{ $area->slug }}">{{ $area->name }}</option>
                            @endforeach
                            <option value="other">Somewhere else</option>
                        </select>
                    </x-site.field>
                    <x-site.field for="zip" label="ZIP" :error="$errors->first('zip') ? 'Enter a 5-digit ZIP.' : null" class="sm:col-span-2">
                        <input id="zip" type="text" wire:model="zip" autocomplete="postal-code" inputmode="numeric" maxlength="5" class="{{ $input }}" @error('zip') aria-invalid="true" aria-describedby="zip-error" @enderror>
                    </x-site.field>
                    <div x-show="$wire.town === 'other'" x-cloak class="sm:col-span-6">
                        <x-site.field for="otherTown" label="Which town?" :error="$errors->first('otherTown') ? 'Tell us the town.' : null">
                            <input id="otherTown" type="text" wire:model="otherTown" autocomplete="address-level2" class="{{ $input }}" @error('otherTown') aria-invalid="true" aria-describedby="otherTown-error" @enderror>
                        </x-site.field>
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" wire:model="smsConsent" class="mt-1 size-5 rounded border-navy-300 text-signal-600 focus:ring-signal-500">
                        <span class="text-navy-800">You can text me about this request. <span class="text-sm text-navy-600">Message and data rates may apply.</span></span>
                    </label>
                    @error('smsConsent') <p class="text-sm font-semibold text-red-700">Check this box so we can text you, or choose another way to reach you.</p> @enderror
                    <label class="flex items-start gap-3">
                        <input type="checkbox" wire:model="marketingConsent" class="mt-1 size-5 rounded border-navy-300 text-signal-600 focus:ring-signal-500">
                        <span class="text-navy-800">Send me a reminder to get my door ready before hurricane season. <span class="text-sm text-navy-600">Once a year, and you can unsubscribe.</span></span>
                    </label>
                </div>
            </div>
        @endif

        @if ($step === QuoteWizard::REVIEW)
            <div class="space-y-6">
                <h2 class="font-display text-3xl font-extrabold tracking-wide text-navy-900 uppercase">Look it over</h2>

                <dl class="divide-y divide-navy-100 rounded-xl ring-1 ring-navy-100">
                    @php
                        $area = $this->towns->firstWhere('slug', $town);
                        $rows = [
                            [QuoteWizard::SERVICE, 'Service', $selected->name ?? 'Something else'],
                            [QuoteWizard::DETAILS, 'Details', implode("\n", $this->detailLines())],
                            [QuoteWizard::PHOTOS, 'Photos', count($photos) === 0 ? 'None' : count($photos).' '.Str::plural('photo', count($photos))],
                            [QuoteWizard::CONTACT, 'Contact', implode("\n", [$name, $phone, $email, 'Prefers: '.ContactMethod::tryFrom($preferredContact)?->getLabel(), $street.', '.($area->name ?? $otherTown).' '.$zip])],
                        ];
                    @endphp
                    @foreach ($rows as [$goto, $label, $value])
                        <div class="flex gap-4 px-4 py-4 sm:px-6">
                            <dt class="w-20 shrink-0 font-semibold text-navy-900 sm:w-24">{{ $label }}</dt>
                            <dd class="min-w-0 flex-1 whitespace-pre-line wrap-anywhere text-navy-700">{{ trim($value) }}</dd>
                            <dd><button type="button" wire:click="goTo({{ $goto }})" class="text-sm font-semibold text-signal-700 underline underline-offset-2 hover:text-navy-900">Edit<span class="sr-only"> {{ strtolower($label) }}</span></button></dd>
                        </div>
                    @endforeach
                </dl>

                @error('turnstileToken') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                @error('submit') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
            </div>
        @endif

        {{-- Rendered once, at page load, and kept out of the steps; it only shows itself if Cloudflare needs the visitor to click. --}}
        @if (QuoteWizard::usesTurnstile())
            <div
                wire:ignore
                x-data="{ widget: null }"
                x-init="
                    const render = () => widget = window.turnstile.render($el, {
                        sitekey: @js(config('services.turnstile.key')),
                        action: 'quote',
                        appearance: 'interaction-only',
                        callback: (token) => $wire.turnstileToken = token,
                        'expired-callback': () => $wire.turnstileToken = '',
                        'error-callback': () => $wire.turnstileToken = '',
                    });
                    window.turnstile ? render() : window.addEventListener('turnstile:ready', render, { once: true });
                "
                x-on:turnstile-reset.window="widget !== null && window.turnstile.reset(widget)"
            ></div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-navy-100 pt-6">
            @if ($step > QuoteWizard::SERVICE)
                <button type="button" wire:click="back" class="inline-flex items-center gap-2 font-semibold text-navy-700 hover:text-navy-900">
                    <x-heroicon-o-arrow-left class="size-5" aria-hidden="true" /> Back
                </button>
            @else
                <span></span>
            @endif

            @if ($step === QuoteWizard::REVIEW)
                <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="inline-flex items-center gap-2 rounded-md bg-signal-500 px-7 py-4 text-lg font-bold text-navy-900 transition hover:bg-signal-400 disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">Send my request</span>
                    <span wire:loading wire:target="submit">Sending…</span>
                </button>
            @else
                <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-md bg-signal-500 px-7 py-4 text-lg font-bold text-navy-900 transition hover:bg-signal-400 disabled:opacity-60">
                    {{ $step === QuoteWizard::PHOTOS && $photos === [] ? 'Skip photos' : 'Next' }} <x-heroicon-o-arrow-right class="size-5" aria-hidden="true" />
                </button>
            @endif
        </div>
    </form>
</div>
