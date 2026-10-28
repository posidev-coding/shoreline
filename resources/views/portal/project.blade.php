@php
    use App\Enums\ProjectStage;
    use App\Enums\VisitStatus;
    $isFinished = in_array($project->stage, ProjectStage::finished(), true);
@endphp
<x-layouts.portal :title="$project->title">
    <a href="{{ route('portal.dashboard') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-navy-700 hover:text-navy-900">
        <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />
        Your projects
    </a>

    <h1 class="mt-4 font-display text-4xl font-extrabold tracking-wide text-navy-900 uppercase">{{ $project->title }}</h1>
    <p class="mt-1 text-navy-700">{{ $project->property->address() }}</p>
    @if ($isFinished && $project->completed_at)
        <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-navy-50 px-3 py-1 text-sm font-semibold text-navy-800">
            <x-heroicon-o-check-circle class="size-4" aria-hidden="true" />
            Finished {{ $project->completed_at->timezone(config('app.business_timezone'))->format('M j, Y') }}
        </p>
    @endif

    <section aria-labelledby="visits" class="mt-10">
        <h2 id="visits" class="font-display text-2xl font-extrabold tracking-wide text-navy-900 uppercase">Visits</h2>

        @if ($upcoming->isEmpty() && $past->isEmpty())
            <p class="mt-4 rounded-lg bg-white p-5 text-navy-700 ring-1 ring-navy-100">No visits scheduled yet. We’ll be in touch to set a time.</p>
        @endif

        @if ($upcoming->isNotEmpty())
            <ul class="mt-4 space-y-3">
                @foreach ($upcoming as $visit)
                    <li class="rounded-lg bg-white p-5 ring-1 ring-navy-100">
                        <p class="font-semibold text-navy-900">{{ $visit->window() }}</p>
                        <p class="text-navy-700">{{ $visit->type->getLabel() }}</p>
                        <p class="mt-2 text-sm {{ $visit->status === VisitStatus::Confirmed ? 'font-semibold text-navy-800' : 'text-navy-700' }}">
                            {{ $visit->status === VisitStatus::Confirmed ? 'Confirmed' : 'We’ll confirm the time with you.' }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($past->isNotEmpty())
            <h3 class="mt-8 text-sm font-bold tracking-widest text-navy-600 uppercase">Earlier</h3>
            <ul class="mt-3 divide-y divide-navy-100 rounded-lg bg-white ring-1 ring-navy-100">
                @foreach ($past as $visit)
                    <li class="flex flex-wrap justify-between gap-x-4 px-5 py-3 text-navy-800">
                        <span>{{ $visit->type->getLabel() }}</span>
                        <span class="text-sm text-navy-700">{{ $visit->window() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.portal>
