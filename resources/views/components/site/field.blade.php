@props(['label', 'for', 'error' => null, 'hint' => null, 'optional' => false])
{{-- A labelled form field on the light site background. Pass the field's error message in. --}}
<div {{ $attributes }}>
    <label for="{{ $for }}" class="block font-semibold text-navy-900">
        {{ $label }}
        @if ($optional)
            <span class="font-normal text-navy-600">(optional)</span>
        @endif
    </label>
    @if ($hint)
        <p id="{{ $for }}-hint" class="mt-1 text-sm text-navy-600">{{ $hint }}</p>
    @endif
    <div class="mt-2">{{ $slot }}</div>
    @if ($error)
        <p id="{{ $for }}-error" class="mt-2 text-sm font-semibold text-red-700">{{ $error }}</p>
    @endif
</div>
