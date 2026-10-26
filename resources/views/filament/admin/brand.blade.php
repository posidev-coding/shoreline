{{-- Panel logo: the wordmark in navy, or sand in dark mode. --}}
<x-site.wordmark :id="$dark ? 'admin-dark' : 'admin'" @class(['h-full w-auto', 'text-[#e8dcc4]' => $dark, 'text-[#0b1f33]' => ! $dark]) />
