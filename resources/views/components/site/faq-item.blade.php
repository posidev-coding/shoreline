@props(['faq'])
<details class="group px-5 py-4 [&_summary::-webkit-details-marker]:hidden">
    <summary class="flex cursor-pointer list-none items-start justify-between gap-4 text-lg font-bold text-navy-900">
        {{ $faq->question }}
        <x-heroicon-o-chevron-down class="mt-1 size-5 shrink-0 text-signal-700 transition group-open:rotate-180" aria-hidden="true" />
    </summary>
    <p class="mt-3 leading-relaxed text-navy-700">{{ $faq->answer }}</p>
</details>
