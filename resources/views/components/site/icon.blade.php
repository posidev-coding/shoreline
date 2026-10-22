@props(['name'])
{{-- Line icons for services (App\Enums\ServiceIcon), drawn on a 24px grid. --}}
@php($name = $name instanceof \App\Enums\ServiceIcon ? $name->value : $name)
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('install')
            <path d="M3 10.5 12 4l9 6.5V20H3z" />
            <path d="M6.5 20v-7.5h11V20M6.5 15h11M6.5 17.5h11" />
            @break
        @case('repair')
            <path d="M14.7 6.3a4 4 0 0 0 5 5L21 13l-.5.5a5 5 0 0 1-6.2.7L8 20.5a2.1 2.1 0 0 1-3-3l6.3-6.3a5 5 0 0 1 .7-6.2L12.5 4z" />
            @break
        @case('spring')
            <path d="M4 4h16M4 20h16" />
            <path d="m7 4 10 2-10 2 10 2-10 2 10 2-10 2 10 2-3 2" />
            @break
        @case('opener')
            <rect x="4" y="9" width="12" height="9" rx="1.5" />
            <path d="M8 18v2M12 18v2M7 13h6" />
            <path d="M18 7a4 4 0 0 1 0 6M20.5 4.5a8 8 0 0 1 0 11" />
            @break
        @case('wind')
            <path d="M3 8h11a3 3 0 1 0-3-3" />
            <path d="M3 12h15a3 3 0 1 1-3 3" />
            <path d="M3 16h7" />
            @break
        @case('toolbox')
            <rect x="3" y="8" width="18" height="12" rx="1.5" />
            <path d="M8 8V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V8M3 13h18M10 13v2h4v-2" />
            @break
    @endswitch
</svg>
