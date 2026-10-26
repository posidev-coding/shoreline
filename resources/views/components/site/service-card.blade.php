@props(['service'])
<a href="{{ route('services.show', $service) }}" {{ $attributes->class('group flex h-full flex-col rounded-xl border border-navy-100 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-signal-500 hover:shadow-md') }}>
    <span class="flex size-12 items-center justify-center rounded-lg bg-navy-900 text-signal-500">
        <x-site.icon :name="$service->icon" class="size-7" />
    </span>
    <h3 class="mt-5 font-display text-2xl font-extrabold tracking-wide text-navy-900 uppercase">{{ $service->name }}</h3>
    <p class="mt-2 flex-1 leading-relaxed text-navy-700">{{ $service->summary }}</p>
    <span class="mt-4 inline-flex items-center gap-1 font-semibold text-signal-700 group-hover:text-navy-900">
        Learn more <x-heroicon-o-arrow-right class="size-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
    </span>
</a>
