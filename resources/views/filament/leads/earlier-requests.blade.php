@php
    /** @var \App\Models\Lead $record */
    $record = $getRecord();
@endphp

<ul class="space-y-2 text-sm">
    @foreach ($record->earlierRequests()->with('service')->limit(10)->get() as $earlier)
        <li>
            <a href="{{ \App\Filament\Resources\Leads\LeadResource::getUrl('view', ['record' => $earlier]) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                {{ $earlier->service->name ?? 'Something else' }}
            </a>
            <span class="text-gray-500 dark:text-gray-400">· {{ $earlier->created_at->toFormattedDateString() }} · {{ $earlier->status->getLabel() }}</span>
        </li>
    @endforeach
</ul>
