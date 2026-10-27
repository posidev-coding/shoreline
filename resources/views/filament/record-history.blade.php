<x-filament-panels::page>
    @php($activities = $this->getActivities())

    <div class="space-y-4">
        @forelse ($activities as $activity)
            @php($changes = $this->changes($activity))
            <x-filament::section compact>
                <x-slot name="heading">
                    {{ $activity->causer?->name ?? 'The system' }}
                    <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $activity->description === $activity->event ? ucfirst($activity->event) : $activity->description }} · {{ $this->when($activity) }}</span>
                </x-slot>

                @if ($changes !== [])
                    <dl class="grid grid-cols-[minmax(7rem,auto)_1fr] gap-x-4 gap-y-2 text-sm">
                        @foreach ($changes as $change)
                            <dt class="font-medium text-gray-950 dark:text-white">{{ $change['field'] }}</dt>
                            <dd class="text-gray-700 dark:text-gray-300">
                                @if ($activity->event === 'updated')
                                    <span class="text-gray-500 line-through dark:text-gray-400">{{ $change['old'] }}</span>
                                    <span aria-hidden="true">→</span>
                                    <span class="sr-only">changed to</span>
                                @endif
                                {{ $activity->event === 'deleted' ? $change['old'] : $change['new'] }}
                            </dd>
                        @endforeach
                    </dl>
                @endif
            </x-filament::section>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No changes recorded yet.</p>
        @endforelse

        <x-filament::pagination
            currentPageOptionProperty="recordsPerPage"
            :page-options="$this->getRecordsPerPageSelectOptions()"
            :paginator="$activities"
        />
    </div>
</x-filament-panels::page>
