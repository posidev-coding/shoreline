<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-flag" icon-color="warning" heading="Before the site goes live">
        <ul class="list-disc space-y-1 ps-5 text-sm">
            @foreach (\App\Filament\Widgets\LaunchChecklist::items() as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
