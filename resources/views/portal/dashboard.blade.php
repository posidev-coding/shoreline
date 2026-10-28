@php
    use App\Enums\VisitStatus;
    $firstName = str($user->name)->before(' ')->toString();
@endphp
<x-layouts.portal title="Your projects">
    <h1 class="font-display text-4xl font-extrabold tracking-wide text-navy-900 uppercase">Hi{{ $firstName !== '' ? ' '.$firstName : '' }}</h1>

    @if ($nextVisit)
        <section aria-labelledby="next-visit" class="mt-8 rounded-lg bg-navy-900 p-6 text-sand-100">
            <h2 id="next-visit" class="text-sm font-bold tracking-widest text-signal-400 uppercase">Next visit</h2>
            <p class="mt-2 font-display text-3xl font-extrabold tracking-wide text-white uppercase">{{ $nextVisit->window() }}</p>
            <p class="mt-2">{{ $nextVisit->type->getLabel() }} for {{ $nextVisit->project->title }}, at {{ $nextVisit->project->property->street }}</p>
            @if ($nextVisit->status === VisitStatus::Tentative)
                <p class="mt-1 text-sm text-sand-200/80">We’ll confirm the time with you.</p>
            @endif
        </section>
    @endif

    <section aria-labelledby="active" class="mt-10">
        <h2 id="active" class="font-display text-2xl font-extrabold tracking-wide text-navy-900 uppercase">Your projects</h2>

        @forelse ($active as $project)
            <a href="{{ route('portal.projects.show', $project->ulid) }}" class="mt-4 block rounded-lg bg-white p-5 shadow-sm ring-1 ring-navy-100 transition hover:ring-signal-500">
                @if ($showClientNames)
                    <p class="text-xs font-bold tracking-widest text-navy-600 uppercase">{{ $project->client->display_name }}</p>
                @endif
                <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-lg font-bold text-navy-900">
                    {{ $project->title }}
                    @include('portal.partials.unread', ['count' => (int) ($unread[$project->getKey()] ?? 0)])
                </p>
                <p class="text-navy-700">{{ $project->property->street }}, {{ $project->property->city }}</p>
                <p class="mt-3 flex items-center gap-2 text-sm text-navy-800">
                    <x-heroicon-o-calendar-days class="size-4 shrink-0 text-signal-700" aria-hidden="true" />
                    @if ($project->nextVisit)
                        {{ $project->nextVisit->type->getLabel() }}, {{ $project->nextVisit->window() }}{{ $project->nextVisit->status === VisitStatus::Tentative ? ' (to be confirmed)' : '' }}
                    @else
                        No visit scheduled yet
                    @endif
                </p>
            </a>
        @empty
            <p class="mt-4 rounded-lg bg-white p-5 text-navy-700 ring-1 ring-navy-100">Nothing under way right now. When we start on a job for you, it shows up here.</p>
        @endforelse
    </section>

    @if ($finished->isNotEmpty())
        <section aria-labelledby="finished" class="mt-10">
            <h2 id="finished" class="font-display text-2xl font-extrabold tracking-wide text-navy-900 uppercase">Finished</h2>
            <ul class="mt-4 divide-y divide-navy-100 rounded-lg bg-white ring-1 ring-navy-100">
                @foreach ($finished as $project)
                    <li>
                        <a href="{{ route('portal.projects.show', $project->ulid) }}" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-4 hover:bg-sand-50">
                            <span>
                                <span class="font-semibold text-navy-900">{{ $project->title }}</span>
                                @include('portal.partials.unread', ['count' => (int) ($unread[$project->getKey()] ?? 0)])
                                <span class="block text-sm text-navy-700">{{ $project->property->street }}, {{ $project->property->city }}</span>
                            </span>
                            @if ($project->completed_at)
                                <span class="text-sm text-navy-700">Finished {{ $project->completed_at->timezone(config('app.business_timezone'))->format('M j, Y') }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.portal>
