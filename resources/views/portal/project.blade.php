@php
    use App\Enums\ProjectStage;
    use App\Enums\VisitStatus;
    use App\Support\PrivateMedia;
    $isFinished = in_array($project->stage, ProjectStage::finished(), true);
    $expires = PrivateMedia::expiry();
    $timezone = config('app.business_timezone');
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

    @if ($updates->isNotEmpty())
        <section aria-labelledby="updates" class="mt-10">
            <h2 id="updates" class="font-display text-2xl font-extrabold tracking-wide text-navy-900 uppercase">Updates</h2>
            <ol class="mt-4 space-y-3">
                @foreach ($updates as $update)
                    <li class="rounded-lg bg-white p-5 ring-1 ring-navy-100">
                        <p class="text-sm font-semibold text-navy-700">
                            <time datetime="{{ $update->created_at?->toIso8601String() }}">{{ $update->created_at?->timezone($timezone)->format('D, M j, Y') }}</time>
                        </p>
                        <p class="mt-1 whitespace-pre-line text-navy-900">{{ $update->body }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <livewire:portal.messages :project="$project" />

    @if ($photos->isNotEmpty())
        <section aria-labelledby="photos" class="mt-10">
            <h2 id="photos" class="font-display text-2xl font-extrabold tracking-wide text-navy-900 uppercase">Photos</h2>
            <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($photos as $photo)
                    @php
                        $thumb = PrivateMedia::thumbnailUrl($photo, $expires);
                    @endphp
                    <li>
                        <a href="{{ PrivateMedia::url($photo, $expires) }}" target="_blank" rel="noopener" class="group block overflow-hidden rounded-lg bg-white ring-1 ring-navy-100 hover:ring-signal-500">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="Photo {{ $loop->iteration }} of {{ $project->title }}" loading="lazy" class="aspect-4/3 w-full object-cover transition group-hover:opacity-90">
                            @else
                                <span class="flex aspect-4/3 w-full flex-col items-center justify-center gap-1 p-3 text-center text-sm text-navy-700">
                                    <x-heroicon-o-photo class="size-6" aria-hidden="true" />
                                    Photo {{ $loop->iteration }}
                                </span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-sm text-navy-700">Tap a photo to see it full size.</p>
        </section>
    @endif

    @if ($documents->isNotEmpty())
        <section aria-labelledby="documents" class="mt-10">
            <h2 id="documents" class="font-display text-2xl font-extrabold tracking-wide text-navy-900 uppercase">Documents</h2>
            <ul class="mt-4 divide-y divide-navy-100 rounded-lg bg-white ring-1 ring-navy-100">
                @foreach ($documents as $document)
                    <li>
                        <a href="{{ PrivateMedia::url($document, $expires) }}" target="_blank" rel="noopener" class="flex items-center gap-3 px-5 py-4 hover:bg-sand-50">
                            <x-heroicon-o-document-text class="size-6 shrink-0 text-signal-700" aria-hidden="true" />
                            <span class="min-w-0">
                                <span class="block font-semibold break-words text-navy-900">{{ $document->name }}</span>
                                <span class="block text-sm text-navy-700">{{ strtoupper(pathinfo($document->file_name, PATHINFO_EXTENSION)) }}, {{ $document->human_readable_size }}</span>
                            </span>
                            <x-heroicon-o-arrow-down-tray class="ms-auto size-5 shrink-0 text-navy-600" aria-hidden="true" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.portal>
